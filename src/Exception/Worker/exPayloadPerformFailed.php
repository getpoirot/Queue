<?php
namespace Poirot\Queue\Exception\Worker;

use Poirot\Queue\Payload\FailedPayload;


class exPayloadPerformFailed
    extends \RuntimeException
{
    /** @var FailedPayload */
    protected $payload;


    /**
     * exPayloadPerformFailed constructor.
     *
     * @param FailedPayload $payload
     * @param \Exception $reason
     */
    function __construct(FailedPayload $payload, \Exception $reason)
    {
        $this->payload = $payload;

        parent::__construct($reason->getMessage(), $payload->getCountRetries(), $reason);
    }


    // ..

    /**
     * Failed Payload
     *
     * @return FailedPayload
     */
    function getPayload()
    {
        return $this->payload;
    }

    /**
     * Exception Reason
     *
     * @return \Exception
     */
    function getReason()
    {
        return $this->getPrevious();
    }
}
