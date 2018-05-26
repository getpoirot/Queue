<?php
namespace Poirot\Queue\Payload;

use Poirot\Queue\Interfaces\iPayloadQueued;


class FailedPayload
    extends QueuedPayload
{
    /** @var iPayloadQueued */
    protected $qPayloadWrapper;

    protected $countRetries = 0;


    /**
     * BasePayload constructor.
     *
     * @param mixed $payload
     * @param int   $countTries
     */
    function __construct(iPayloadQueued $payload, $countTries = null)
    {
        $this->qPayloadWrapper = $payload;

        if (null !== $countTries)
            $this->setCountRetries($countTries);
    }


    // Wrapper

    /**
     * Get Storage UID for This Payload
     *
     * @return mixed
     */
    function getUID()
    {
        return $this->qPayloadWrapper->getUID();
    }

    /**
     * Get Payload Content
     *
     * @return mixed Serializable content
     */
    function getData()
    {
        return $this->qPayloadWrapper->getData();
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
        $qPayload = clone $this->qPayloadWrapper;
        $qPayload = $qPayload->withData($data);
        $n->qPayloadWrapper = $qPayload;
        return $n;
    }

    /**
     * Get Queue Name
     *
     * @return string Queue name
     */
    function getQueue()
    {
        return $this->qPayloadWrapper->getQueue();
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
        $qPayload = clone $this->qPayloadWrapper;
        $qPayload = $qPayload->withQueue($queue);
        $n->qPayloadWrapper = $qPayload;
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
        $qPayload = clone $this->qPayloadWrapper;
        $qPayload = $qPayload->withUID($uid);
        $n->qPayloadWrapper = $qPayload;
        return $n;
    }

    /**
     * Get Created Timestamp
     *
     * @return int Timestamp
     */
    function getCreatedTimestamp()
    {
        return $this->qPayloadWrapper->getCreatedTimestamp();
    }


    // Implement Failed Payload

    function getCountRetries()
    {
        return $this->countRetries;
    }

    function incCountRetries()
    {
        $this->countRetries++;
        return $this;
    }

    function setCountRetries($countRetries)
    {
        $this->countRetries = (int) $countRetries;
        return $this;
    }


    // Implement Serializable:

    /**
     * @inheritdoc
     */
    function serialize()
    {
        $s = [
            'wrp' => serialize($this->qPayloadWrapper),
            'try' => $this->getCountRetries(),
        ];

        return serialize($s);
    }

    /**
     * @inheritdoc
     */
    function unserialize($serialized)
    {
        $us = unserialize($serialized);
        $this->qPayloadWrapper = unserialize($us['wrp']);
        $this->countRetries    = $us['try'];
    }
}
