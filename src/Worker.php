<?php
namespace Poirot\Queue;

use Poirot\Events\Interfaces\iEvent;
use Poirot\Events\Interfaces\iEventHeap;
use Poirot\Events\Interfaces\Respec\iEventProvider;
use Poirot\Queue\Interfaces\iQueueDriver;
use Poirot\Queue\Queue\AggregateQueue;
use Poirot\Queue\Queue\InMemoryQueue;
use Poirot\Queue\Worker\EventHeapOfWorker;
use Poirot\Std\ConfigurableSetter;
use Poirot\Std\Exceptions\exImmutable;
use Poirot\Storage\FlatFileStore;
use Poirot\Storage\Interfaces\iDataStore;


class Worker
    extends ConfigurableSetter
    implements iEventProvider
{
    /** @var string Worker name */
    protected $name;
    /** @var AggregateQueue */
    protected $queue;

    /** @var iEventHeap */
    protected $events;
    /** @var iDataStore */
    protected $storage;
    /** @var iQueueDriver */
    protected $builtinQueue;


    /**
     * Worker constructor.
     *
     * @param string         $name
     * @param AggregateQueue $queue
     * @param array          $settings
     */
    function __construct($name, AggregateQueue $queue, $settings = null)
    {
        $this->name  = (string) $name;
        $this->queue = $queue;

        if ($settings !== null)
            parent::__construct($settings);


        $this->__init();
    }


    function __init()
    {

    }


    function go()
    {
        # Achieve Max Execution Time
        #
        ini_set('max_execution_time', 0);
        set_time_limit(0);

        // TODO Move to demon
        // allow the script to run forever
        ignore_user_abort(true);


        # Go For Jobs
        #
        while ( 1 )
        {

        }
    }


    // Options:

    /**
     * Give Storage Object To Worker
     *
     * @param iDataStore $storage
     *
     * @return $this
     */
    function giveStorage(iDataStore $storage)
    {
        if ($this->storage)
            throw new exImmutable(sprintf(
                'Storage (%s) is given.'
                , \Poirot\Std\flatten($this->storage)
            ));


        $this->storage = $storage;
        return $this;
    }

    /**
     * Storage
     *
     * @return iDataStore
     */
    protected function _getStorage()
    {
        if (! $this->storage) {
            $realm = str_replace('\\', '_', get_class($this));
            $this->giveStorage( new FlatFileStore($realm.'__'.$this->name) );
        }

        return $this->storage;
    }

    /**
     * Give Built In Queue Driver
     *
     * @param iQueueDriver $queueDriver
     *
     * @return $this
     */
    function giveBuiltInQueue(iQueueDriver $queueDriver)
    {
        if ($this->builtinQueue)
            throw new exImmutable(sprintf(
                'Built-in Queue (%s) is given.'
                , \Poirot\Std\flatten($this->builtinQueue)
            ));


        $this->builtinQueue = $queueDriver;
        return $this;
    }

    /**
     * Get Built In Queue Driver
     *
     * @return iQueueDriver
     */
    protected function _getBuiltInQueue()
    {
        if (! $this->builtinQueue )
            $this->giveBuiltInQueue( new InMemoryQueue );


        return $this->builtinQueue;
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
            $this->events = new EventHeapOfWorker;

        return $this->events;
    }
}
