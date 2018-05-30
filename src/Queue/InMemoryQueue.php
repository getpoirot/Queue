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
        $payload = clone $payload;


        if (null === $queue && $payload instanceof iPayloadQueued)
            $queue = $payload->getQueue();

        $qPayload = $payload;
        if (! $payload instanceof iPayloadQueued ) {
            $qPayload = new QueuedPayload($payload);
            $qPayload = $qPayload
                ->withUID( \Poirot\Std\generateUniqueIdentifier(24) )
            ;
        }

        /** @var QueuedPayload $qPayload */
        $qPayload = $qPayload->withQueue( $this->_normalizeQueueName($queue) );


        ## Persist Queued Payload
        #
        $uid      = $qPayload->getUID();
        $qName    = $qPayload->getQueue();
        $time     = $qPayload->getCreatedTimestamp();

        if (! isset($this->queues[$qName]) )
            $this->queues[$qName] = [];

        $ps = &$this->queues[$qName];
        $ps[$uid] = [
            'id'      => $uid,
            'queue'   => $qName,
            'payload' => $qPayload,
            'created_timestamp' => $time,
        ];


        return $qPayload;
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