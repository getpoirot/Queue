<?php
namespace Poirot\Queue\Interfaces;


interface iQueueDriver
{
    /**
     * Push To Queue
     *
     * @param string   $queue
     * @param iPayload $payload Serializable payload
     *
     * @return iPayloadQueued
     */
    function push($queue, $payload);

    /**
     * Pop From Queue
     *
     * @param string $queue
     *
     * @return iPayloadQueued|null
     */
    function pop($queue);

    /**
     * Remove an Specific From Queue
     *
     * @param iPayloadQueued|string $queue
     * @param null|string           $id
     *
     * @return void
     */
    function del($queue, $id);

    /**
     * Get Queue Size
     *
     * @param string $queue
     *
     * @return int
     */
    function size($queue);

    /**
     * Get Queues List
     *
     * @return []string
     */
    function listQueues();
}
