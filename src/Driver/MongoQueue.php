<?php
namespace Poirot\Queue\Driver;

use MongoDB;

use Poirot\Queue\Interfaces\iPayload;
use Poirot\Queue\Interfaces\iPayloadQueued;
use Poirot\Queue\Interfaces\iQueueDriver;
use Poirot\Queue\Payload\QueuedPayload;
use Poirot\Storage\Interchange\SerializeInterchange;


// TODO Handle Errors

class MongoQueue
    implements iQueueDriver
{
    /** @var MongoDB\Collection */
    protected $collection;

    private static $typeMap = [
        'root' => 'array',
        'document' => 'array',
        'array' => 'array',
    ];


    /**
     * MongoQueue constructor.
     *
     * @param MongoDB\Collection $collection
     */
    function __construct(MongoDB\Collection $collection)
    {
        $this->collection = $collection;
    }


    /**
     * Push To Queue
     *
     * @param string $queue
     * @param iPayload $payload Serializable payload
     *
     * @return iPayloadQueued
     */
    function push($queue, $payload)
    {
        $payload = $payload->getPayload();

        $this->collection->insertOne(
            [
                '_id'     => $uid = new \MongoId(),
                'queue'   => $this->_normalizeQueueName($queue),
                'payload' => __(new SerializeInterchange())->makeForward( $payload ),
                'created_timestamp' => time(),
            ]
        );

        $queued = new QueuedPayload($payload);
        $queued = $queued
            ->withUID($uid)
            ->withQueue($queue)
        ;

        return $queued;
    }

    /**
     * Pop From Queue
     *
     * @param string $queue
     *
     * @return iPayloadQueued|null
     */
    function pop($queue)
    {
        $queued = $this->collection->findOne(
            [
                'queue' => $this->_normalizeQueueName($queue),
            ]
            , [
                // pick last one in the queue
                'sort'    => [ '_id' => -1 ],
                // override typeMap option
                'typeMap' => self::$typeMap,
            ]
        );

        if (! $queued )
            return null;


        $payload = __(new SerializeInterchange())
            ->retrieveBackward($queued['payload']);

        $payload = new QueuedPayload($payload);
        $payload = $payload->withQueue($queue)
            ->withUID($queued['_id']);

        return $payload;
    }

    /**
     * Remove an Specific From Queue
     *
     * @param iPayloadQueued|string $queue
     * @param null|string           $id
     *
     * @return void
     */
    function del($queue, $id)
    {
        if ($queue instanceof iPayloadQueued) {
            $id    = $queue->getUID();
            $queue = $queue->getQueue();
        }

        $this->collection->deleteOne([
            '_id'   => $id,
            'queue' => $this->_normalizeQueueName($queue),
        ]);
    }

    /**
     * Get Queue Size
     *
     * @param string $queue
     *
     * @return int
     */
    function size($queue)
    {
        $count = $this->collection->count(
            [
                'queue' => $this->_normalizeQueueName($queue),
            ]
        );

        return $count;
    }

    /**
     * Get Queues List
     *
     * @return []string
     */
    function listQueues()
    {
        $csr = $this->collection->aggregate(
            [
                '$group' => [
                    '_id'   => [ '$queue' ],
                ],
            ]
            , [
                // override typeMap option
                'typeMap' => self::$typeMap,
            ]
        );

        $list = [];
        foreach ($csr as $item)
            $list[] = $item['_id'];

        return $list;
    }


    // ..

    protected function _normalizeQueueName($queue)
    {
        return strtolower( (string) $queue );
    }
}