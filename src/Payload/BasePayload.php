<?php
namespace Poirot\Queue\Payload;

use Poirot\Queue\Interfaces\iPayload;


/**
 * Other Payload can extend this
 *
 * - Assert On Payload Valuable
 *
 */
class BasePayload
    implements iPayload
{
    protected $payload;


    /**
     * BasePayload constructor.
     *
     * @param mixed $payload
     */
    function __construct($payload)
    {
        $this->payload = $payload;
    }


    /**
     * Get Payload Content
     *
     * @return mixed Serializable content
     */
    function getPayload()
    {
        return $this->payload;
    }

    /**
     * With Given Payload
     *
     * @param mixed $payload Serializable payload
     *
     * @return $this
     */
    function withPayload($payload)
    {
        $n = clone $this;
        $n->payload = $payload;
        return $n;
    }
}
