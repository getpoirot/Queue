<?php
namespace Poirot\Queue;

use Poirot\Events\Interfaces\iEvent;
use Poirot\Events\Interfaces\iEventHeap;
use Poirot\Events\Interfaces\Respec\iEventProvider;
use Poirot\Queue\Interfaces\iQueueDriver;
use Poirot\Queue\Queue\EventHeapOfQueue;
use Poirot\Std\ConfigurableSetter;
use Poirot\Std\Exceptions\exImmutable;


class Worker
    extends ConfigurableSetter
    implements iEventProvider
{
    /** @var string */
    protected $channel;
    /** @var iQueueDriver */
    protected $queueDriver;
    /** @var iEventHeap */
    protected $events;


    /**
     * Set Queue Channel Name
     *
     * @param string $channel
     *
     * @return $this
     */
    function setChannel($channel)
    {
        $this->channel = (string) $channel;
        return $this;
    }

    /**
     * Get Queue Channel Name
     *
     * @return string
     */
    function getChannel()
    {
        return $this->channel;
    }

    /**
     * Give Queue Driver
     *
     * @param iQueueDriver $queueDriver
     *
     * @return $this
     * @throws exImmutable
     */
    function giveQueueDriver(iQueueDriver $queueDriver)
    {
        $this->queueDriver = $queueDriver;
        return $this;
    }


    // Implement Event Aware

    /**
     * Get Events
     *
     * @return iEvent
     */
    function event()
    {
        if (! $this->events)
            $this->events = new EventHeapOfQueue;

        return $this->events;
    }
}
