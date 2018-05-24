<?php
namespace Poirot\Queue\Queue;

use Poirot\Queue\Exception\exIOError;
use Poirot\Queue\Exception\exReadError;
use Poirot\Queue\Exception\exWriteError;
use Poirot\Queue\Interfaces\iPayload;
use Poirot\Queue\Interfaces\iPayloadQueued;
use Poirot\Queue\Interfaces\iQueueDriver;
use Poirot\Queue\Payload\QueuedPayload;
use Poirot\Storage\Interchange\SerializeInterchange;


class PdoQueue
    implements iQueueDriver
{
    /** @var \PDO */
    protected $conn;

    /** @var SerializeInterchange */
    protected $_c_interchangable;


    /**
     * Constructor.
     *
     * @param \PDO $conn
     */
    function __construct(\PDO $conn)
    {
        $this->conn = $conn;
    }


    /**
     * Push To Queue
     *
     * @param iPayload $payload Serializable payload
     * @param string   $queue
     *
     * @return iPayloadQueued
     * @throws exIOError
     */
    function push($payload, $queue = null)
    {
        $payload  = $payload->getPayload();
        $sPayload = $this->_interchangeable()
            ->makeForward($payload);

        try {

            $uid   = \Poirot\Std\generateUniqueIdentifier(24);
            $qName = $this->_normalizeQueueName($queue);
            $time  = time();

            $sql = "INSERT INTO `Queue` 
                   (`task_id`, `queue_name`, `payload`, `created_timestamp`, `is_pop`)
                   VALUES ('$uid', '$qName', '$sPayload', '$time', '0');
            ";

            if ( false === $this->conn->exec($sql) )
                throw new \Exception( $this->conn->errorInfo() );


        } catch (\Exception $e) {
            throw new exWriteError('Error While Write To PDO Client.', $e->getCode(), $e);
        }

        $queued = new QueuedPayload($payload);
        $queued = $queued
            ->withUID( (string) $uid)
            ->withQueue($queue)
        ;

        return $queued;
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
        $qName = $this->_normalizeQueueName($queue);

        try
        {
            // Find
            //
            $this->conn->beginTransaction();

            $sql = "SELECT * FROM `Queue` WHERE `queue_name` = '$qName' and `is_pop` = 0;";
            $stm = $this->conn->prepare($sql);
            $stm->execute();
            $stm->setFetchMode(\PDO::FETCH_ASSOC);
            $queued = $stm->fetch();


            // Update
            //
            if ( $queued ) {
                $sql = "UPDATE `Queue` SET `is_pop` = 1 WHERE `task_id` = '{$queued['task_id']}';";
                $this->conn->exec($sql);
            }


            $this->conn->commit();

        } catch (\Exception $e) {
            throw new exReadError(sprintf('Error While Read From PDO Client: (%s).', $e->getCode()), 0, $e);
        }

        if (! $queued )
            // Nothing In Queue..
            return null;



        $payload = $this->_interchangeable()
            ->retrieveBackward($queued['payload']);

        $payload = new QueuedPayload($payload);
        $payload = $payload
            ->withQueue($queue)
            ->withUID( (string) $queued['task_id'] )
        ;

        return $payload;
    }

    /**
     * Release an Specific From Queue By Removing It
     *
     * @param iPayloadQueued|string $id
     * @param null|string           $queue
     *
     * @return void
     * @throws exIOError
     */
    function release($id, $queue = null)
    {
        if ( $id instanceof iPayloadQueued ) {
            $arg   = $id;
            $id    = $arg->getUID();
            $queue = $arg->getQueue();
        }


        $queue = $this->_normalizeQueueName($queue);

        try {

            $sql = "DELETE FROM `Queue` WHERE `task_id` = '{$id}' and `queue_name` = '{$queue}';";
            $this->conn->exec($sql);

        } catch (\Exception $e) {
            throw new exWriteError('Error While Delete From MySql Client.', $e->getCode(), $e);
        }
    }

    /**
     * Find Queued Payload By Given ID
     *
     * @param string $id
     * @param string $queue
     *
     * @return iPayloadQueued|null
     * @throws exIOError
     */
    function findByID($id, $queue = null)
    {
        $queue = $this->_normalizeQueueName($queue);

        try {
            $sql = "SELECT * FROM `Queue` WHERE `queue_name` = '$queue' and `task_id` = '$id';";

            $stm = $this->conn->prepare($sql);
            $stm->execute();
            $stm->setFetchMode(\PDO::FETCH_ASSOC);

            $queued = $stm->fetch();

        } catch (\Exception $e) {
            throw new exReadError('Error While Read From MySql Client.', $e->getCode(), $e);
        }


        if (! $queued )
            return null;


        $payload = $this->_interchangeable()
            ->retrieveBackward( $queued['payload'] );

        $payload = new QueuedPayload($payload);
        $payload = $payload->withQueue($queue)
            ->withUID( (string) $queued['task_id'] );

        return $payload;
    }

    /**
     * Get Queue Size
     *
     * @param string $queue
     *
     * @return int
     * @throws exIOError
     */
    function size($queue = null)
    {
        $queue = $this->_normalizeQueueName($queue);


        try {
            $sql = "SELECT COUNT(*) as `count_queue` FROM `Queue` WHERE `queue_name` = '$queue';";

            $stm = $this->conn->prepare($sql);
            $stm->execute();
            $stm->setFetchMode(\PDO::FETCH_ASSOC);

            $queued = $stm->fetch();

        } catch (\Exception $e) {
            throw new exReadError('Error While Read From MySql Client.', $e->getCode(), $e);
        }

        return (int) $queued['count_queue'];
    }

    /**
     * Get Queues List
     *
     * @return string[]
     * @throws exIOError
     */
    function listQueues()
    {
        try {

            $sql = "SELECT `queue_name` FROM `Queue` GROUP BY `queue_name`;";

            $stm = $this->conn->prepare($sql);
            $stm->execute();
            $stm->setFetchMode(\PDO::FETCH_ASSOC);

            $csr = $stm->fetchAll();


        } catch (\Exception $e) {
            throw new exReadError('Error While Read From MySql Client.', $e->getCode(), $e);
        }

        $list = [];
        foreach ($csr as $item)
            $list[] = $item['queue_name'];

        return $list;
    }


    // ..

    /**
     * @return SerializeInterchange
     */
    protected function _interchangeable()
    {
        if (! $this->_c_interchangable)
            $this->_c_interchangable = new SerializeInterchange;

        return $this->_c_interchangable;
    }

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
