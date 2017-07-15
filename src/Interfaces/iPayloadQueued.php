<?php
namespace Poirot\Queue\Interfaces;


interface iPayloadQueued
    extends iPayload
{
    /**
     * Get Storage UID for This Payload
     *
     * @return mixed
     */
    function getUID();

    /**
     * Get Queue Name
     *
     * @return string Queue name
     */
    function getQueue();

    /**
     * Queue Name
     *
     * @param string $queue
     *
     * @return $this
     */
    function withQueue($queue);

    /**
     * With UID
     *
     * @param mixed $uid
     *
     * @return $this
     */
    function withUID($uid);

    /**
     * Get Created Timestamp
     *
     * @return int Timestamp
     */
    function getCreatedTimestamp();
}
