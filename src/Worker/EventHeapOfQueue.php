<?php
namespace Poirot\Queue\Queue;

use Poirot\Events\Event;
use Poirot\Events\EventHeap;


class EventHeapOfQueue
    extends EventHeap
{
    const EVENT_BEFORE_EXEC = 'queue.before.exec';
    const EVENT_AFTER_EXEC  = 'queue.after.exec';


    /**
     * Initialize
     *
     */
    function __init()
    {
        $this->collector = new DataCollector;

        // attach default event names:
        $this->bind(new Event(self::EVENT_BEFORE_EXEC));
        $this->bind(new Event(self::EVENT_AFTER_EXEC));
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

}