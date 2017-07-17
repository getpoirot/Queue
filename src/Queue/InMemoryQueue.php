<?php
namespace Poirot\Queue\Queue;

use Poirot\Queue\Exception\exIOError;
use Poirot\Queue\Interfaces\iPayload;
use Poirot\Queue\Interfaces\iPayloadQueued;
use Poirot\Queue\Interfaces\iQueueDriver;
use Poirot\Queue\Payload\QueuedPayload;


class InMemoryQueue
    implements iQueueDriver
{
    protected $queues = [];


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

        $id = uniqid();
        $queueName = $this->_normalizeQueueName($queue);

        if (! isset($this->queues[$queueName]) )
            $this->queues[$queueName] = [];

        $ps = &$this->queues[$queueName];
        $ps[$id] = [
            'id'      => $id,
            'queue'   => $queueName,
            'payload' => $payload,
            'created_timestamp' => time(),
        ];

        $queued = new QueuedPayload($payload);
        $queued = $queued
            ->withUID($id)
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
        $queue = $this->_normalizeQueueName($queue);

        if (! isset($this->queues[$queue]) )
            return null;

        if ( null === $item = array_pop($this->queues[$queue]) )
            return null;


        $payload = $item['payload'];

        $payload = new QueuedPayload($payload);
        $payload = $payload->withQueue($queue)
            ->withUID( (string) $item['id'] );

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
            $arg   = $id;
            $id    = $arg->getUID();
            $queue = $arg->getQueue();
        }

        if (! isset($this->queues[$queue]) )
            return;

        if (! isset($this->queues[$queue][(string)$id]) )
            return;


        unset($this->queues[$queue][(string)$id]);
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
        $queue = $this->_normalizeQueueName($queue);

        if (! isset($this->queues[$queue]) )
            return null;

        if (! isset($this->queues[$queue][(string)$id]) )
            return null;


        $item = $this->queues[$queue][(string)$id];

        $payload = $item['payload'];

        $payload = new QueuedPayload($payload);
        $payload = $payload->withQueue($queue)
            ->withUID( (string) $id );

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
        $queue = $this->_normalizeQueueName($queue);

        return (isset($this->queues[$queue])) ? count($this->queues[$queue]) : 0;
    }

    /**
     * Get Queues List
     *
     * @return string[]
     * @throws exIOError
     */
    function listQueues()
    {
        return array_keys($this->queues);
    }


    // ..

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