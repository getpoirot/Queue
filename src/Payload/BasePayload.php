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
    protected $data;


    /**
     * BasePayload constructor.
     *
     * @param mixed $payload
     */
    function __construct($payload)
    {
        $this->data = $payload;
    }


    /**
     * Get Payload Content
     *
     * @return mixed Serializable content
     */
    function getData()
    {
        return $this->data;
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
        $n->data = $data;
        return $n;
    }


    // Implement Serializable:

    /**
     * @inheritdoc
     */
    function serialize()
    {
        $s = [
            'dt' => serialize( $this->getData() )
        ];

        return serialize($s);
    }

    /**
     * @inheritdoc
     */
    function unserialize($serialized)
    {
        $us = unserialize($serialized);
        $this->data = unserialize($us['dt']);
    }
}
