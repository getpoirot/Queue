<?php
namespace Poirot\Queue\Queue;

use Poirot\Queue\Exception\exIOError;
use Poirot\Queue\Interfaces\iPayloadQueued;
use Poirot\Queue\Interfaces\iQueueDriver;
use Poirot\Std\Type\StdArray;


/**
 * Used To Implement Cron Likes Tasks
 * that run each n time cycle
 *
 */
class SteadyQueue
    extends InMemoryQueue
    implements iQueueDriver
{
    protected $queues    = [];
    protected $_defaults = [];


    /**
     * SteadyQueue constructor.
     *
     * @param array|null $defaultQueues
     */
    function __construct(array $defaultQueues = null)
    {
        if ($defaultQueues !== null)
            $this->setDefaultQueues($defaultQueues);

    }


    /**
     * Pop From Queue
     *
     * note: when you pop a message from queue you have to
     *       release it when worker done with it.
     *
     *
     * @param string $queue
     *
     * @return iPayloadQueued|null
     * @throws exIOError
     */
    function pop($queue = null)
    {
        if ( null === $j = parent::pop($queue) && !empty($this->queues) )
            // Set Default Once The Queue Is Empty
            $this->setDefaultQueues($this->_defaults);

        else return $j;


        // Try To Pop Again; With defaults queue values ...
        return parent::pop($queue);
    }



    // Options:

    /**
     * Set Default Jobs In Queue
     *
     * @param array $defaultQueues
     *
     * @return $this
     */
    function setDefaultQueues(array $defaultQueues)
    {
        $this->_defaults = $defaultQueues;

        $queues = StdArray::of($this->queues)->withMergeRecursive($defaultQueues, true);
        $this->queues = $queues->value;

        return $this;
    }


    // ..

    /**
     * @param string $queue
     * @return string
     */
    protected function _normalizeQueueName($queue)
    {
        if ($queue === null)
            return $queue = 'general';

        return strtolower( (string) $queue );
    }
}