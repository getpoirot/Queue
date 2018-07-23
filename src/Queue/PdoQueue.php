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
    protected $table;

    /** @var SerializeInterchange */
    protected $_c_interchangable;


    /**
     * Constructor.
     *
     * @param \PDO   $conn
     * @param string $table
     */
    function __construct(\PDO $conn, $table = null)
    {
        $this->conn  = $conn;
        $this->table = ($table === null) ? 'Queue' : (string) $table;
    }


    /**
     * Push To Queue
     *
     * @param iPayload|iPayloadQueued $payload Serializable payload
     * @param string   $queue
     *
     * @return iPayloadQueued
     * @throws exIOError
     */
    function push($payload, $queue = null)
    {
        $payload = clone $payload;


        if (null === $queue && $payload instanceof iPayloadQueued)
            $queue = $payload->getQueue();

        try
        {
            $qPayload = $payload;
            if (! $payload instanceof iPayloadQueued ) {
                $qPayload = new QueuedPayload($payload);
                $qPayload = $qPayload
                    ->withUID( \Poirot\Std\generateUniqueIdentifier(24) )
                ;
            }

            $qPayload = $qPayload->withQueue( $this->_normalizeQueueName($queue) );


            ## Persist Queued Payload
            #
            $uid      = $qPayload->getUID();
            $qName    = $qPayload->getQueue();
            $sPayload = addslashes(serialize($qPayload));
            $time     = $qPayload->getCreatedTimestamp();

            $sql = "INSERT INTO `{$this->table}` 
                   (`task_id`, `queue_name`, `payload`, `created_timestamp`, `is_pop`)
                   VALUES ('$uid', '$qName', '$sPayload', '$time', '0');
            ";

            if ( false === $this->conn->exec($sql) )
                throw new \Exception( $this->conn->errorInfo() );


        } catch (\Exception $e) {
            throw new exWriteError($e->getMessage(), $e->getCode(), $e);
        }


        ## Persist Queue Job
        #
        return $qPayload;
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

            $sql = "SELECT * FROM `{$this->table}` WHERE `queue_name` = '$qName' and `is_pop` = 0;";
            $stm = $this->conn->prepare($sql);
            $stm->execute();
            $stm->setFetchMode(\PDO::FETCH_ASSOC);
            $queued = $stm->fetch();


            // Update
            //
            if ($queued ) {
                $sql = "UPDATE `{$this->table}` SET `is_pop` = 1 WHERE `task_id` = '{$queued['task_id']}';";
                $this->conn->exec($sql);
            }


            $this->conn->commit();

        } catch (\Exception $e) {
            throw new exReadError(sprintf('Error While Read From PDO Client: (%s).', $e->getCode()), 0, $e);
        }

        if (! $queued )
            // Nothing In Queue..
            return null;


        $payload = unserialize($queued['payload']);
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

            $sql = "DELETE FROM `{$this->table}` WHERE `task_id` = '{$id}' and `queue_name` = '{$queue}';";
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
            $sql = "SELECT * FROM `{$this->table}` WHERE `queue_name` = '$queue' and `task_id` = '$id';";

            $stm = $this->conn->prepare($sql);
            $stm->execute();
            $stm->setFetchMode(\PDO::FETCH_ASSOC);

            if (! $queued = $stm->fetch() )
                return null;

        } catch (\Exception $e) {
            throw new exReadError('Error While Read From MySql Client.', $e->getCode(), $e);
        }


        $payload = unserialize($queued['payload']);
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
            $sql = "SELECT COUNT(*) as `count_queue` FROM `{$this->table}` WHERE `queue_name` = '$queue';";

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

            $sql = "SELECT `queue_name` FROM `{$this->table}` GROUP BY `queue_name`;";

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
