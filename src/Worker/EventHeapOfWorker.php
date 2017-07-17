<?php
namespace Poirot\Queue\Worker;

use Poirot\Events\Event;
use Poirot\Events\EventHeap;
use Poirot\Queue\Interfaces\iPayloadQueued;


class EventHeapOfWorker
    extends EventHeap
{
    const EVENT_PAYLOAD_RECEIVED = 'worker.payload.received';
    const EVENT_PAYLOAD_FAILURE  = 'worker.after.exec';

    /**
     * Initialize
     *
     */
    function __init()
    {
        $this->collector = new DataCollector;

        // attach default event names:
        $this->bind(new Event(self::EVENT_PAYLOAD_RECEIVED));
        $this->bind(new Event(self::EVENT_PAYLOAD_FAILURE));
    }

    /**
     * @override ide auto info
     * @inheritdoc
     *
     * @return DataCollector
     */
    function collector($options = null)
    {
        return parent::collector($options);
    }
}

class DataCollector
    extends \Poirot\Events\Event\DataCollector
{
    /** @var iPayloadQueued */
    protected $receivedPayload;


    /**
     * @return mixed
     */
    public function getReceivedPayload()
    {
        return $this->receivedPayload;
    }

    /**
     * @param mixed $receivedPayload
     */
    public function setReceivedPayload($receivedPayload)
    {
        $this->receivedPayload = $receivedPayload;
    }
}