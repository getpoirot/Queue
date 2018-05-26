<?php
namespace Poirot\Queue\Payload;

use Poirot\Queue\Interfaces\iPayload;
use Poirot\Queue\Interfaces\iPayloadQueued;


class QueuedPayload
    extends BasePayload
    implements iPayloadQueued
{
    protected $uid;
    /** @var iPayload|BasePayload */
    protected $payload;
    protected $queue;
    protected $timeCreated;


    /**
     * Constructor.
     *
     * @param iPayload $payload
     */
    function __construct(iPayload $payload)
    {
        $this->payload = $payload;
    }

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
     * Get Payload Queued
     *
     * @return iPayload
     */
    function getPayload()
    {
        return $this->payload;
    }

    /**
     * With Given Payload
     *
     * @param iPayload $payload
     *
     * @return $this
     */
    function withPayload(iPayload $payload)
    {
        $n = clone $this;
        $n->payload = $payload;
        return $n;
    }

    /**
     * Get Payload Content
     *
     * @return mixed Serializable content
     */
    function getData()
    {
        return $this->payload->getData();
    }

    /**
     * With Given Payload
     *
     * @param mixed $data Serializable payload
     *
     * @return $this
     */
    function withData($data)
    {
        $n = clone $this;
        $p = clone $this->payload;
        $p = $p->withData($data);
        $n->payload = $p;
        return $n;
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
        if (! $this->timeCreated )
            $this->timeCreated = time();


        return $this->timeCreated;
    }


    // Implement Serializable:

    /**
     * @inheritdoc
     */
    function serialize()
    {
        $s = [
            'uid' => $this->getUID(),
            'qn'  => $this->getQueue(),
            'pl'  => serialize( $this->getPayload() ),
            'tm'  => $this->getCreatedTimestamp(),
        ];

        return serialize($s);
    }

    /**
     * @inheritdoc
     */
    function unserialize($serialized)
    {
        $us = unserialize($serialized);
        $this->uid         = $us['uid'];
        $this->queue       = $us['qn'];
        $this->payload     = unserialize($us['pl']);
        $this->timeCreated = $us['tm'];
    }
}
