<?php
namespace Poirot\Queue\Worker;

use Poirot\Events\Event;
use Poirot\Events\EventHeap;
use Poirot\Queue\Interfaces\iPayloadQueued;


class EventHeapOfWorker
    extends EventHeap
{
    const EVENT_PAYLOAD_RECEIVED = 'worker.payload.received';
    const EVENT_PAYLOAD_FAILURE  = 'worker.max.retries.exceeded';
    const EVENT_PAYLOAD_SUCCEED  = 'worker.after.successful.exec';
    const EVENT_PAYLOAD_RETRY    = 'worker.perform.fail';


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
        $this->bind(new Event(self::EVENT_PAYLOAD_SUCCEED));
        $this->bind(new Event(self::EVENT_PAYLOAD_RETRY));
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
    protected $payload;


    /**
     * @return mixed
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * @param mixed $payload
     */
    public function setPayload($payload)
    {
        $this->payload = $payload;
    }
}