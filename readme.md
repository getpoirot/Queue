# Queue

Consumer

```php
$client     = \Module\MongoDriver\Actions::Driver()->getClient('master');
$collection = $client->selectCollection('papioniha', 'queue.app');


$q = new MongoQueue($collection);

$message = (object)['ver'=>'0.1', 'to'=>'naderi.payam@gmail.com', 'template'=>'welcome'];
$qd      = $q->push('send-mails', new BasePayload($message) );

// message was queued on: 1500196427
// stdClass Object ( [ver] => 0.1 [to] => naderi.payam@gmail.com [template] => welcome )
print_r('message was queued on: '. $qd->getCreatedTimestamp() );
print_r( $qd->getPayload() );

$qID = $qd->getUID(); // 596b2e4bed473800180dfdb2
```

Producer

```php
$client     = \Module\MongoDriver\Actions::Driver()->getClient('master');
$collection = $client->selectCollection('papioniha', 'queue.app');

$q = new MongoQueue($collection);
while ($QueuedMessage = $q->pop('send-mails')) {
    $payload = $QueuedMessage->getPayload();
    switch ($payload->ver) {
        case '0.1':
            print_r($payload);
            // $mail->sendTo($payload->to, $payload->tempate);
            break;
    }


    $q->release($QueuedMessage);
}
```

## Aggregate Queue With Priority Weight

```php
$client     = \Module\MongoDriver\Actions::Driver()->getClient('master');
$collection = $client->selectCollection('papioniha', 'queue.app');

$qMongo = new MongoQueue($collection);


# Build Aggregate Queue
#
$qAggregate = new AggregateQueue([
    // Send Authorization SMS With Higher Priority
    'send-sms-auth'   => [ $qMongo, 0.9 ],
    // Normal Messages
    'send-sms-notify' => [ $qMongo, 0.2 ],
]);


# Add To Queue
#
for ($i =0; $i<1000; $i++) {
    $postfix = random_int(0, 9999);
    $code    = random_int(0, 9999);
    $message = (object)['ver'=>'0.1', 'to'=>'0935549'.$postfix, 'template'=>'auth', 'code' => $code];

    $qAggregate->push(new BasePayload($message), 'send-sms-auth');
}

for ($i =0; $i<1000; $i++) {
    $postfix = random_int(0, 9999);
    $message = (object)['ver'=>'0.1', 'to'=>'0935549'.$postfix, 'template'=>'welcome'];

    $qAggregate->push(new BasePayload($message), 'send-sms-notify');
}


# Pop From Queue send SMS
#
while ( $payload = $qAggregate->pop() ) {
    $size = $qAggregate->size('send-sms-auth');
    if (!isset($skip) && $size == 0 ) {
        $behind = $qAggregate->size('send-sms-notify');
        print_r(sprintf('Sending Auth SMS Done While Normal Queue Has (%s) in list.', $behind));
        echo '<br/>';

        $skip = true;
    }

    // release message
    $qAggregate->release($payload);
}
```
