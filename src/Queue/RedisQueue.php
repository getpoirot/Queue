<?php
namespace Poirot\Queue\Queue;

use Predis;

use Poirot\Queue\Exception\exIOError;
use Poirot\Queue\Exception\exReadError;
use Poirot\Queue\Exception\exWriteError;
use Poirot\Queue\Exception\NotImplementedException;
use Poirot\Queue\Interfaces\iPayload;
use Poirot\Queue\Interfaces\iPayloadQueued;
use Poirot\Queue\Interfaces\iQueueDriver;
use Poirot\Queue\Payload\QueuedPayload;
use Poirot\Storage\Interchange\SerializeInterchange;

class RedisQueue implements iQueueDriver
{

    const QUEUE_PREFIX = "queue.";
    const DEFAULT_QUEUE_NAME = "general";
    const WIP_QUEUE_PREFIX = "wip_queue.";

    /** @var  Predis\Client */
    protected $client;

    /** @var SerializeInterchange */
    protected $_c_interchangable;

    /**
     * MongoQueue constructor.
     *
     * @param Predis\Client $client
     */
    function __construct(Predis\Client $client)
    {
        $this->client = $client;
    }

    /**
     * Push To Queue
     *
     * @param iPayload $payload Serializable payload
     * @param string $queue
     *
     * @return iPayloadQueued
     * @throws exIOError
     */
    function push($payload, $queue = null)
    {
        $payload  = $payload->getData();

        $uid = ($payload instanceof iPayloadQueued)
            ? $payload->getUID()
            : \Poirot\Std\generateUniqueIdentifier(24);

        if ($queue === null && $payload instanceof iPayloadQueued)
            $queue = $payload->getQueue();

        $qName = $this->_normalizeQueueName($queue);

        $time = ($payload instanceof iPayloadQueued)
            ? $time = $payload->getCreatedTimestamp()
            : time();


        $value = $this->_interchangeable()
            ->makeForward([
                'id'                => $uid,
                'payload'           => $payload,
                'created_timestamp' => $time,
        ]);

        try
        {
            $this->client->lpush($qName, $value);
        } catch (\Exception $e)
        {
            throw new exWriteError('Error While Writing To Redis Client.', $e->getCode(), $e);
        }

        $queued = new QueuedPayload($payload);
        $queued = $queued
            ->withUID($uid)
            ->withQueue($qName);

        return $queued;
    }

    /**
     * Pop From Queue
     *
     * @param string $queue
     *
     * @return iPayloadQueued|null
     * @throws exIOError
     */
    function pop($queue = null)
    {
        $queueName = $this->_normalizeQueueName($queue);

        $rpop = null;
        try
        {
            $rpop = $this->client->rpop($queueName);
        } catch(\Exception $e) {
            throw new exReadError('Error While Reading From Redis Client.', $e->getCode(), $e);
        }

        if (empty($rpop))
        {
            return null;
        }

        $rpopArray = (array) $this->_interchangeable()->retrieveBackward($rpop);

        try
        {
            $this->client->hset($this->_getWIPQueueName($queue), $rpopArray['id'], $rpop);
        } catch (\Exception $e)
        {
            throw new exWriteError('Error While Writing To Redis Client.', $e->getCode(), $e);
        }

        $payload = $rpopArray['payload'];
        $payload = new QueuedPayload($payload);
        $payload = $payload->withQueue($queue)
            ->withUID( (string) $rpopArray['id'] );

        return $payload;
    }

    /**
     * Release an Specific From Queue By Removing It
     *
     * @param iPayloadQueued|string $id
     * @param null|string $queue
     *
     * @return void
     * @throws exIOError
     */
    function release($id, $queue = null)
    {
        if ( $id instanceof iPayloadQueued )
        {
            $arg   = $id;
            $id    = $arg->getUID();
            $queue = $arg->getQueue();
        }

        try
        {
            $this->client->hdel($this->_getWIPQueueName($queue), $id);
        } catch (\Exception $e) {
            throw new exWriteError('Error While Writing To Redis Client.', $e->getCode(), $e);
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
        throw new NotImplementedException("For God's sake, Cannot Implement this feature in Redis!");
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
        $count = 0;

        try {
            $count = $this->client->llen($this->_normalizeQueueName($queue));
        } catch (\Exception $e) {
            throw new exReadError('Error While Reading From Redis Client.', $e->getCode(), $e);
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
            return $this->client->keys(self::QUEUE_PREFIX . "*");
        } catch (\Exception $e) {
            throw new exReadError('Error While Reading From Redis Client.', $e->getCode(), $e);
        }
    }

    /**
     * @param string $queue
     * @return string
     */
    protected function _normalizeQueueName($queue)
    {
        if ($queue === null)
        {

            return self::QUEUE_PREFIX.self::DEFAULT_QUEUE_NAME;
        }

        return \strtolower( self::QUEUE_PREFIX . (string) $queue );
    }

    /**
     * @param string $queue
     * @return string
     */
    protected function _getWIPQueueName($queue)
    {
        if ($queue === null)
        {
            $queue = self::DEFAULT_QUEUE_NAME;
        }
        return \strtolower( self::WIP_QUEUE_PREFIX . (string) $queue );
    }

    /**
     * @return SerializeInterchange
     */
    protected function _interchangeable()
    {
        if (! $this->_c_interchangable)
            $this->_c_interchangable = new SerializeInterchange;

        return $this->_c_interchangable;
    }

}