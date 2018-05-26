<?php
namespace Poirot\Queue;

use Poirot\Queue\Interfaces\iPayload;


/**
 * When Payload Received; Listener Attached To Event
 *
 * @see Worker
 */
class aJob
{
    function __invoke(array $data = null, iPayload $payload = null, Worker $worker = null)
    {
        // Implement This ...
        //
    }
}
