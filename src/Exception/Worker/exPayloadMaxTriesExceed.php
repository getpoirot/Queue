<?php
namespace Poirot\Queue\Exception\Worker;


class exPayloadMaxTriesExceed
    extends \RuntimeException
{
    protected $payload;


    function __construct($payload, $message, $code, \Exception $previous = null)
    {
        $this->payload = $payload;

        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string
     */
    function getPayload()
    {
        return $this->payload;
    }
}