<?php
namespace Poirot\Queue\Interfaces;


interface iPayload
{
    /**
     * Get Payload Content
     *
     * @return mixed Serializable content
     */
    function getPayload();
}
