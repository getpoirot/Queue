<?php
namespace Poirot\Queue\Payload;

use Poirot\Queue\Interfaces\iPayloadQueued;


class QueuedPayload
    extends BasePayload
    implements iPayloadQueued
{
    protected $uid;
    protected $queue;


    /**
     * Get Storage UID for This Payload
     *
     * @return mixed
     */
    function getUID()
    {
        return $this->uid;
    }

    /**
     * Get Queue Name
     *
     * @return string Queue name
     */
    function getQueue()
    {
        return $this->queue;
    }

    /**
     * Queue Name
     *
     * @param string $queue
     *
     * @return $this
     */
    function withQueue($queue)
    {
        $n = clone $this;
        $n->queue = $queue;
        return $n;
    }

    /**
     * With UID
     *
     * @param mixed $uid
     *
     * @return $this
     */
    function withUID($uid)
    {
        $n = clone $this;
        $n->uid = $uid;
        return $n;
    }

    /**
     * Get Created Timestamp
     *
     * @return int Timestamp
     */
    function getCreatedTimestamp()
    {
        return time();
    }
}
