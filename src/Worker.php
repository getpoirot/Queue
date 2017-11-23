<?php
namespace Poirot\Queue;

use Poirot\Events\Interfaces\iEventHeap;
use Poirot\Events\Interfaces\Respec\iEventProvider;
use Poirot\Queue\Exception\Worker\exPayloadMaxTriesExceed;
use Poirot\Queue\Exception\Worker\exPayloadPerformFailed;
use Poirot\Queue\Interfaces\iPayloadQueued;
use Poirot\Queue\Interfaces\iQueueDriver;
use Poirot\Queue\Payload\BasePayload;
use Poirot\Queue\Queue\AggregateQueue;
use Poirot\Queue\Queue\InMemoryQueue;
use Poirot\Queue\Worker\EventHeapOfWorker;
use Poirot\Queue\Worker\Events\PayloadReceived\ListenerExecutePayload;
use Poirot\Std\ConfigurableSetter;
use Poirot\Std\Exceptions\exImmutable;


class Worker
    extends ConfigurableSetter
    implements iEventProvider
{
    protected $_worker_id;
    /** @var string Worker name */
    protected $workerName;
    /** @var AggregateQueue */
    protected $queue;

    /** @var iEventHeap */
    protected $events;
    /** @var iQueueDriver */
    protected $builtinQueue;


    protected $maxTries = 3;
    /** @var int Second(s) */
    protected $blockingInterval = 3;
    /** @var int Second(s) */
    protected $sleep = 0.5 * 1000000;


    /**
     * Worker constructor.
     *
     * @param string         $workerName
     * @param AggregateQueue $queue
     * @param array          $settings
     */
    function __construct($workerName, AggregateQueue $queue, $settings = null)
    {
        $this->workerName  = (string) $workerName;

        $this->queue = $queue;

        if ($settings !== null)
            parent::__construct($settings);
    }

    protected function __init()
    {
        $this->_attachDefaultEvents();


        $self = $this;
        register_shutdown_function(function () use ($self) {
            $self->__destruct();
        });
    }

    /**
     * Get Worker Name
     *
     * @return string
     */
    function getWorkerName()
    {
        return $this->workerName;
    }

    /**
     * Get Worker ID
     *
     * @return string
     */
    function getWorkerID()
    {
        if (! $this->_worker_id )
            $this->_worker_id  = uniqid();

        return $this->_worker_id;
    }


    /**
     *
     * @return int Executed job
     */
    function goUntilEmpty()
    {
        # Achieve Max Execution Time
        #
        ini_set('max_execution_time', 0);
        set_time_limit(0);


        # Add Default Queues To Control Follow
        #
        $failedQueue  = $this->_getBuiltInQueue();
        if (! in_array('failed', $this->queue->listQueues()) )
            // Check whether queue with name failed exists or not
            $this->queue->addQueue('failed', $failedQueue, 9);


        # Go For Jobs
        #
        $jobExecuted = 0;
        while ( 1 )
        {
            try {

                ## Pop a Payload from Queue
                #
                $originPayload = \Poirot\Std\reTry(
                    function () {
                        // Pop Payload form Queue
                        return $this->queue->pop();
                    }
                    , $this->getMaxTries()
                    , $this->getBlockingInterval()
                );

                if ($originPayload === null)
                    // Queue is empty
                    break;



                ## Push To Process Payload and Release Queue So Child Processes can continue
                #
                $processPayload = \Poirot\Std\reTry(
                    function() use ($originPayload) {
                        return $this->_getBuiltInQueue()->push($originPayload, 'processing');
                    }
                    , $this->getMaxTries()
                    , $this->getBlockingInterval()
                );

                ## Release Queue So Child Processes can continue
                #
                $flagOriginReleased = \Poirot\Std\reTry(
                    function() use ($originPayload) {
                        $this->queue->release($originPayload);
                        return true;
                    }
                    , $this->getMaxTries()
                    , $this->getBlockingInterval()
                );


                ## Perform Payload Execution
                #
                $this->performPayload($processPayload);

            }

            catch (exPayloadPerformFailed $e) {

                ## Push Back Payload as Failed Message To Queue Again For Next Process
                #
                \Poirot\Std\reTry(
                    function() use ($e) {
                        // Build Message For Performer
                        // @see self::performPayload
                        $message = [ 'failed', $e->getTries(), $e->getWhy()->getMessage(), $e->getPayload() ];
                        return $this->_getBuiltInQueue()->push( new BasePayload($message), 'failed' );
                    }
                    , $this->getMaxTries()
                    , $this->getBlockingInterval()
                );

            }

            catch (exPayloadMaxTriesExceed $e) {
                // Log Failed Messages
                $this->event()->trigger(
                    EventHeapOfWorker::EVENT_PAYLOAD_FAILURE
                    , [
                        'workerName' => $this->workerName,
                        'payload' => $e->getPayload(),
                        'exception' => $e
                    ]
                );
            }

            catch (\Exception $e) {

                // Logical Exceptions are OK and not considered as critical errors.

                if ( $e instanceof \LogicException ) {

                    // Log Failed Messages
                    $this->event()->trigger(
                        EventHeapOfWorker::EVENT_PAYLOAD_FAILURE
                        , [
                            'workerName' => $this->workerName,
                            'payload' => $e->getPayload(),
                            'exception' => $e
                        ]
                    );

                } else {

                    // Origin Released from queue and process failed
                    // So It Must Back To List Again
                    if ( isset($flagOriginReleased) ) {
                        \Poirot\Std\reTry(
                            function () use ($originPayload) {
                                // Push Payload Back To Queue
                                return $this->queue->push($originPayload, $originPayload->getQueue());
                            }
                            , $this->getMaxTries()
                            , $this->getBlockingInterval()
                        );
                    }
                }
            }

            ## Release Process From Queue
            #
            if ( isset($processPayload) )
                $this->_getBuiltInQueue()->release($processPayload);

        }

        return $jobExecuted;
    }

    /**
     * Go Running The Worker Processes
     *
     * @param int $maxExecution
     */
    function goWait($maxExecution = null)
    {
        # Go For Jobs
        #
        $jobExecution = 0; $sleep = 0;
        while ( 1 )
        {
            if ( 0 == $executed = $this->goUntilEmpty() ) {
                // List is Empty; Smart Sleep
                $sleep += 100000;
                usleep($sleep);
                if ($sleep > 2 * 1000000)
                    // Sleep more than 2 second not allowed!!
                    $sleep = 100000;

                continue;
            }

            $jobExecution += $executed;
            if ($jobExecution >= $maxExecution)
                // Maximum Execution Task Exceed!!
                break;

            if ( $sleep = $this->getSleep() )
                // Take a breath between hooks
                usleep($sleep);

            $sleep = 0;
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
        $payLoad    = $processPayload->getPayload();
        $triesCount = 0;

        if ( is_array($payLoad) ) {
            // ['failed', 4, 'Request to server X was timeout.', ['ver' => '0.1', 'fun' => 'echo']]
            @list($failedTag, $triesCount, $exceptionWhy) = $payLoad;
            if ($failedTag === 'failed') {
                if ( $triesCount > $this->getMaxTries() )
                    throw new exPayloadMaxTriesExceed($payLoad, 'Max Tries Exceeds.' . $exceptionWhy, null);

                // Retrieve Original Payload
                $payLoad = end($payLoad);
            }
        }

        try {

            if ( ob_get_level() )
                ## clean output buffer, display just error page
                ob_end_clean();
            ob_start();

            $this->event()->trigger(
                EventHeapOfWorker::EVENT_PAYLOAD_RECEIVED
                , [ 'payload' => $payLoad ]
            );

            ob_end_flush(); // Strange behaviour, will not work
            flush();        // Unless both are called !

        } catch (\LogicException $e) {
            // Exception is logical and its ok to throw
            throw $e;

        } catch (\Exception $e) {
            // Process Failed
            // Notify Main Stream
            throw new exPayloadPerformFailed('failed', $triesCount, $payLoad, $e);
        }
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
     * @return int Microsecond
     */
    function getSleep()
    {
        return $this->sleep;
    }

    /**
     * Set Sleep Time Between Payload Retrievals
     *
     * @param int $sleep Microsecond
     *
     * @return $this
     */
    function setSleep($sleep)
    {
        $this->sleep = $sleep;
        return $this;
    }

    /**
     * Get Max Tries On Failed Job
     *
     * @return int
     */
    function getMaxTries()
    {
        return $this->maxTries;
    }

    /**
     * Set Max Tries On Failed Job
     *
     * @param int $maxTries
     *
     * @return $this
     */
    function setMaxTries($maxTries)
    {
        $this->maxTries = (int) $maxTries;
        return $this;
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
     * @return iEventHeap
     */
    function event()
    {
        if (! $this->events)
            $this->events = new EventHeapOfWorker;

        return $this->events;
    }


    // ..

    private function _attachDefaultEvents()
    {
        # Throw Exception if exception not handle on Error Event
        $this->event()->on(
            EventHeapOfWorker::EVENT_PAYLOAD_RECEIVED
            , new ListenerExecutePayload
            , 100
        );
    }

    function __destruct()
    {

    }
}
