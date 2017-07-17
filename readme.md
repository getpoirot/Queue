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

$qAggregate = new AggregateQueue([
    // Send Authorization SMS With Higher Priority
    'send-sms-auth'   => [ $qMongo, 10 ],
    // Normal Messages
    'send-sms-notify' => [ $qMongo, 3 ],
]);


for ($i =0; $i<=100; $i++) {
    $postfix = random_int(0, 9999);
    $code    = random_int(0, 9999);
    $message = (object)['ver'=>'0.1', 'to'=>'0935549'.$postfix, 'template'=>'auth', 'code' => $code];

    $qAggregate->push(new BasePayload($message), 'send-sms-auth');
}

for ($i =0; $i<=600; $i++) {
    $postfix = random_int(0, 9999);
    $message = (object)['ver'=>'0.1', 'to'=>'0935549'.$postfix, 'template'=>'welcome'];

    $qAggregate->push(new BasePayload($message), 'send-sms-notify');
}

while ( $payload = $qAggregate->pop() ) {
    if (!isset($skip) && $qAggregate->size('send-sms-auth') == 0 ) {
        $behind = $qAggregate->size('send-sms-notify');
        print_r(sprintf('Sending Auth SMS Done While Normal Queue Has (%s) in list.', $behind));
        echo '<br/>';
        $skip = true;
    }

    $qAggregate->release($payload);
}
```
