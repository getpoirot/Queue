<?php
namespace Poirot\Queue\Exception\Worker;


class exPayloadPerformFailed
    extends \RuntimeException
{
    protected $payload;


    function __construct($tag, $currTries, $payload, \Exception $previous)
    {
        $this->payload = $payload;

        parent::__construct($tag, $currTries, $previous);
    }

    function getTag()
    {
        return $this->getMessage();
    }

    function getTries()
    {
        return $this->code;
    }

    function getPayload()
    {
        return $this->payload;
    }

    function getWhy()
    {
        return $this->getPrevious();
    }
}
