<?php
namespace Poirot\Queue;

use Poirot\Events\Interfaces\iEvent;
use Poirot\Events\Interfaces\iEventHeap;
use Poirot\Events\Interfaces\Respec\iEventProvider;
use Poirot\Queue\Exception\exIOError;
use Poirot\Queue\Interfaces\iPayload;
use Poirot\Queue\Interfaces\iPayloadQueued;
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
    protected $workerID;
    /** @var AggregateQueue */
    protected $queue;

    /** @var iEventHeap */
    protected $events;
    /** @var iDataStore */
    protected $storage;
    /** @var iQueueDriver */
    protected $builtinQueue;

    /** @var int Second(s) */
    protected $blockingInterval = 3;
    /** @var int Second(s) */
    protected $sleep = 0;


    /**
     * Worker constructor.
     *
     * @param string         $workerID
     * @param AggregateQueue $queue
     * @param array          $settings
     */
    function __construct($workerID, AggregateQueue $queue, $settings = null)
    {
        $this->workerID  = (string) $workerID;
        $this->queue = $queue;

        if ($settings !== null)
            parent::__construct($settings);


        $this->__init();
    }

    function __init()
    {

    }

    /**
     * Get Worker ID
     *
     * @return string
     */
    function getWorkerID()
    {
        return $this->workerID;
    }

    /**
     * Go Running The Worker Processes
     *
     */
    function go()
    {
        # Achieve Max Execution Time
        #
        ini_set('max_execution_time', 0);
        set_time_limit(0);


        # Add Default Queues To Control Follow
        #
        $queueProcess = $this->_getBuiltInQueue();
        $failedQueue  = $this->_getBuiltInQueue();

        $this->queue->addQueue('failed', $failedQueue, 0.9);


        # Go For Jobs
        #
        while ( 1 )
        {
            $retryException = 0;

            try {
                // Pop Payload form Queue
                $originPayload = $this->queue->pop();

                ## Push To Process Payload and Release Queue So Child Processes can continue
                #
                $processPayload = $queueProcess->push($originPayload, 'process');
                // Release Queue So Child Processes can continue
                $this->queue->release($originPayload);

                ## Perform Payload Execution
                #
                $this->performPayload($processPayload);
                // Release Process From Queue
                $queueProcess->release($processPayload);

            } catch (exIOError $e) {
                sleep( $this->getBlockingInterval() );

                $retryException++;
                continue;
            }



            if ($sleep = $this->getSleep())
                // Take a breath between hooks
                sleep($sleep);
        }

    }

    /**
     * Perform Payload Execution
     *
     * @param iPayloadQueued $processPayload
     *
     * @return void
     */
    function performPayload(iPayloadQueued $processPayload)
    {
        // TODO Implement this ...
    }

    // Options:

    /**
     * @return int
     */
    function getBlockingInterval()
    {
        return $this->blockingInterval;
    }

    /**
     * Blocking Interval In Second
     *
     * @param int $blockingInterval
     *
     * @return $this
     */
    function setBlockingInterval($blockingInterval)
    {
        $this->blockingInterval = (int) $blockingInterval;
        return $this;
    }

    /**
     * Get Sleep Time Between Payload Retrievals
     *
     * @return int Second
     */
    function getSleep()
    {
        return $this->sleep;
    }

    /**
     * Set Sleep Time Between Payload Retrievals
     *
     * @param int $sleep Second
     *
     * @return $this
     */
    function setSleep($sleep)
    {
        $this->sleep = $sleep;
        return $this;
    }

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
            $this->giveStorage( new FlatFileStore($realm.'__'.$this->workerID) );
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


    // ..


}
