<?php
namespace Poirot\Queue\Queue;

use MongoDB;

use Poirot\Queue\Exception\exIOError;
use Poirot\Queue\Exception\exReadError;
use Poirot\Queue\Exception\exWriteError;
use Poirot\Queue\Interfaces\iPayload;
use Poirot\Queue\Interfaces\iPayloadQueued;
use Poirot\Queue\Interfaces\iQueueDriver;
use Poirot\Queue\Payload\QueuedPayload;
use Poirot\Storage\Interchange\SerializeInterchange;


/**
 * These Indexes:
 *
 * 	{
 *    "queue": NumberLong(1)
 * }
 * {
 *   "_id": NumberLong(1),
 *   "queue": NumberLong(1)
 * }
 *
 */
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

    /** @var SerializeInterchange */
    protected $_c_interchangable;


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
     * @param iPayload $payload Serializable payload
     * @param string   $queue
     *
     * @return iPayloadQueued
     * @throws exIOError
     */
    function push($payload, $queue = null)
    {
        $payload  = $payload->getPayload();
        $sPayload = $this->_interchangeable()
            ->makeForward($payload);

        try {
            $this->collection->insertOne(
                [
                    '_id'     => $uid = new MongoDB\BSON\ObjectID(),
                    'queue'   => $this->_normalizeQueueName($queue),
                    'payload' => new MongoDB\BSON\Binary($sPayload, MongoDB\BSON\Binary::TYPE_GENERIC),
                    'created_timestamp' => time(),
                ]
            );
        } catch (\Exception $e) {
            throw new exWriteError('Error While Write To Mongo Client.', $e->getCode(), $e);
        }

        $queued = new QueuedPayload($payload);
        $queued = $queued
            ->withUID( (string) $uid)
            ->withQueue($queue)
        ;

        return $queued;
    }

    /**
     * Pop From Queue
     *
     * note: when you pop a message from queue you have to
     *       release it when worker done with it.
     *
     *
     * @param string $queue
     *
     * @return iPayloadQueued|null
     * @throws exIOError
     */
    function pop($queue = null)
    {
        try {
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
        } catch (\Exception $e) {
            throw new exReadError('Error While Write To Mongo Client.', $e->getCode(), $e);
        }

        if (! $queued )
            return null;


        $payload = $this->_interchangeable()
            ->retrieveBackward($queued['payload']);

        $payload = new QueuedPayload($payload);
        $payload = $payload->withQueue($queue)
            ->withUID( (string) $queued['_id'] );

        return $payload;
    }

    /**
     * Release an Specific From Queue By Removing It
     *
     * @param iPayloadQueued|string $id
     * @param null|string           $queue
     *
     * @return void
     * @throws exIOError
     */
    function release($id, $queue = null)
    {
        if ( $id instanceof iPayloadQueued ) {
            $id    = $id->getUID();
            $queue = $id->getQueue();
        }

        try {
            $this->collection->deleteOne([
                '_id'   => new MongoDB\BSON\ObjectID($id),
                'queue' => $this->_normalizeQueueName($queue),
            ]);
        } catch (\Exception $e) {
            throw new exWriteError('Error While Write To Mongo Client.', $e->getCode(), $e);
        }
    }

    /**
     * Find Queued Payload By Given ID
     *
     * @param string $id
     * @param string $queue
     *
     * @return iPayloadQueued|null
     * @throws exIOError
     */
    function findByID($id, $queue = null)
    {
        try {
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
        } catch (\Exception $e) {
            throw new exReadError('Error While Write To Mongo Client.', $e->getCode(), $e);
        }


        if (! $queued )
            return null;


        $payload = $this->_interchangeable()
            ->retrieveBackward($queued['payload']);

        $payload = new QueuedPayload($payload);
        $payload = $payload->withQueue($queue)
            ->withUID( (string) $queued['_id'] );

        return $payload;
    }

    /**
     * Get Queue Size
     *
     * @param string $queue
     *
     * @return int
     * @throws exIOError
     */
    function size($queue = null)
    {
        try {
            $count = $this->collection->count(
                [
                    'queue' => $this->_normalizeQueueName($queue),
                ]
            );
        } catch (\Exception $e) {
            throw new exReadError('Error While Write To Mongo Client.', $e->getCode(), $e);
        }

        return $count;
    }

    /**
     * Get Queues List
     *
     * @return string[]
     * @throws exIOError
     */
    function listQueues()
    {
        try {
            $csr = $this->collection->aggregate(
                [
                    [
                        '$group' => [
                            '_id'   => '$queue',
                        ],
                    ],
                ]
                , [
                    // override typeMap option
                    'typeMap' => self::$typeMap,
                ]
            );
        } catch (\Exception $e) {
            throw new exReadError('Error While Write To Mongo Client.', $e->getCode(), $e);
        }

        $list = [];
        foreach ($csr as $item)
            $list[] = $item['_id'];

        return $list;
    }


    // ..

    /**
     * @return SerializeInterchange
     */
    protected function _interchangeable()
    {
        if (! $this->_c_interchangable)
            $this->_c_interchangable = new SerializeInterchange;

        return $this->_c_interchangable;
    }

    /**
     * @param string $queue
     * @return string
     */
    protected function _normalizeQueueName($queue)
    {
        if ($queue === null)
            return $queue = 'general';

        return strtolower( (string) $queue );
    }
}