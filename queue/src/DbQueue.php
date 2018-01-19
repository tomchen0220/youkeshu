<?php

namespace queue;

/**
 * Class DbQueue
 * @package queue
 * @author longli
 */
class DbQueue implements QueueInterface
{
  /**
   * 数据库驱动
   * @var DbDriver
   */
  public $db;

  /**
   * 配置文件信息
   * @var array
   */
  protected $config = [];

  /**
   * 发布超过多少时间重新放入队列，如果为null则不放入
   * @var int|null
   */
  protected $expire = 600;

  /**
   * @var
   */
  protected $tableName = '';

  /**
   * @inheritdoc
   */
  public function __construct()
  {
    $this->config = require (__DIR__ . '/config.php');
    $this->db = new DbDriver($this->config['db']);
    $this->db->setTableName($this->config['db']['default']['params']['tableName']);
    $this->tableName = $this->db->getTableName();
  }

  /**
   * 推送任务到队列
   * @param mixed $payload  队列对象
   * @param string $queue 队列名称
   * @param integer $delay 多久执行
   * @return string
   */
  public function push(ActiveJob $payload, $queue = null, $delay = 0)
  {
    if(empty($queue))
      $queue = $payload->queueName();
    $payload = json_encode(['payload' => $payload, 'object' => serialize($payload)]);
    $value = [
      ':queue' => $queue,
      ':attempts' => 0,
      ':reserved' => 0,
      ':reserved_at' => null,
      ':payload' => $payload, //Json::encode($payload),
      ':available_at' => time() + $delay,
      ':created_at' => time(),
    ];
    return $this->db->push($this->tableName, $value, '', true);
  }

  /**
   * 从队列中出栈
   * @param string|null $queue 队列名称
   * @return array|false
   */
  public function pop($queue)
  {
    if(!is_null($this->expire))
    {
      //将发布的消息重新放入队列
      $sql = "UPDATE {$this->tableName} SET reserved=0, reserved_at=null, attempts=attempts+1
              WHERE queue=:queue AND reserved=1 AND reserved_at+{$this->expire}<=:time";
      $args = [
        ':queue' => $queue,
        ':time' => time()
      ];
      $this->db->query($sql, $args);
    }
    /**
     * @var \PDO $pdo
     */
    $pdo = $this->db->getPdo();
    if(empty($pdo))
      return null;
    //准备事务
    try
    {
      $pdo->beginTransaction();
      if(($message = $this->receiveMessage($queue)) != null)
      {
        $sql = "UPDATE {$this->tableName} SET reserved=1, reserved_at=:reserved_at WHERE id=:id";
        $args = [
          ':reserved_at' => time(),
          ':id' => $message['id']
        ];
        $this->db->query($sql, $args, '', false);
        $pdo->commit();
        $pdo = null;
        $payload = json_decode($message['payload'], true);
        $message['body'] = array_merge($payload['payload'], ['object' => $payload['object']]);
        return $message;
      }
      if($pdo != null)
      {
        $pdo->commit();
      }
      $pdo = null;
    }
    catch(\PDOException $exception)
    {
      $pdo->rollBack();
    }
    return null;
  }

  /**
   * 从数据库从获取一条队列数据
   * @param  string|null $queue 队列名称
   * @return array|null
   */
  protected function receiveMessage($queue)
  {
    $attempts = $this->config['queue']['attempts'];
    $sql = "SELECT * FROM {$this->tableName} WHERE
           queue=:queue AND reserved=:reserved AND available_at<=:available_at AND attempts<=:attempts LIMIT 1 for update";
    $args = [
      ':queue' => $queue,
      ':reserved' => 0,
      ':available_at' => time(),
      ':attempts' => $attempts
    ];
    $message = $this->db->query($sql, $args, '', false);
    return !empty($message) ? current($message) : null;
  }

  /**
   * 清空队列
   * @param string $queue 队列名称
   */
  public function purge($queue)
  {
    $sql = "DELETE FROM {$this->tableName} WHERE queue=:queue";
    $this->db->query($sql, [':queue' => $queue]);
  }

  /**
   * 重新发布消息
   * @param array $message 消息数据
   * @param integer $delay 执行时间
   */
  public function release(array $message, $delay = 0)
  {
    $sql = "UPDATE {$this->tableName} SET
    attempts=attempts+1,
    available_at=:available_at,
    reserved=:reserved,
    reserved_at=:reserved_at
    WHERE id=:id";
    $args = [
      ':available_at' => time() + $delay,
      ':reserved' => 0,
      ':reserved_at' => null,
      ':id' => $message['id']
    ];
    $this->db->query($sql, $args);
  }

  /**
   * 删除队列消息
   * @param array $message 删除的数据
   */
  public function delete(array $message)
  {
    $sql = "DELETE FROM {$this->tableName} WHERE id=:id";
    $this->db->query($sql, [':id' => $message['id']]);
  }
}
