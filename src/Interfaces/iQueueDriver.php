<?php
namespace Poirot\Queue\Interfaces;

use Poirot\Queue\Exception\exIOError;


interface iQueueDriver
{
    /**
     * Push To Queue
     *
     * @param string   $queue
     * @param iPayload $payload Serializable payload
     *
     * @return iPayloadQueued
     * @throws exIOError
     */
    function push($queue, $payload);

    /**
     * Pop From Queue
     *
     * @param string $queue
     *
     * @return iPayloadQueued|null
     * @throws exIOError
     */
    function pop($queue);

    /**
     * Release an Specific From Queue By Removing It
     *
     * @param iPayloadQueued|string $queue
     * @param null|string           $id
     *
     * @return void
     * @throws exIOError
     */
    function release($queue, $id = null);

    /**
     * Find Queued Payload By Given ID
     *
     * @param string $queue
     * @param string $id
     *
     * @return iPayloadQueued|null
     * @throws exIOError
     */
    function findByID($queue, $id);

    /**
     * Get Queue Size
     *
     * @param string $queue
     *
     * @return int
     * @throws exIOError
     */
    function size($queue);

    /**
     * Get Queues List
     *
     * @return string[]
     * @throws exIOError
     */
    function listQueues();
}
