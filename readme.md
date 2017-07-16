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
