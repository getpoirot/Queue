<?php
namespace Poirot\Queue\Interfaces;


interface iPayload
    extends \Serializable
{
    /**
     * Get Payload Content
     *
     * @return array
     */
    function getData();
}
