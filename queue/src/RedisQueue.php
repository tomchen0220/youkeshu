<?php

namespace queue;

use Predis\Client;
use Predis\Transaction\MultiExec;

/**
 * Class RedisQueue
 * @package queue
 * @author longli
 */
class RedisQueue implements QueueInterface
{
  /**
   * @var Client|array
   */
  public $redis;
  /**
   * @var integer
   */
  public $expire = 60;

  /**
   * @inheritdoc
   */
  public function __construct()
  {
    if(!$this->redis instanceof Client)
    {
      require_once __DIR__ . '/config.php';
      /**
       * @var  array $__CONFIG
       */
      $this->redis = new Client($__CONFIG['redis']);
    }
  }

  /**
   * 添加数据到队列
   * @param mixed $payload 队列对象
   * @param string $queue 队列名称
   * @param integer $delay 执行时间
   * @return string
   */
  public function push(ActiveJob $payload, $queue = null, $delay = 0)
  {
    if(empty($queue))
      $queue = $payload->queueName();
    $payload = json_encode(['id' => $id = md5(uniqid('', true)), 'body' => $payload, 'object' => serialize($payload)]);
    if($delay > 0)
    {
      $this->redis->zadd($queue . ':delayed', [$payload => time() + $delay]);
    }
    else
    {
      $this->redis->rpush($queue, [$payload]);
    }

    return $id;
  }

  /**
   * 从队列中出栈
   * @param string $queue 队列名称
   * @return array|false
   */
  public function pop($queue)
  {
    foreach([':delayed', ':reserved'] as $type)
    {
      $options = ['cas' => true, 'watch' => $queue . $type];
      $this->redis->transaction($options, function(MultiExec $transaction) use ($queue, $type)
      {
        $data = $this->redis->zrangebyscore($queue . $type, '-inf', $time = time());

        if(!empty($data))
        {
          $transaction->zremrangebyscore($queue . $type, '-inf', $time);
          $transaction->rpush($queue, $data);
        }
      });
    }

    $data = $this->redis->lpop($queue);

    if($data === null)
    {
      return false;
    }

    $this->redis->zadd($queue . ':reserved', [$data => time() + $this->expire]);
    $data = json_decode($data, true);
    return [
      'id' => $data['id'],
      'body' => array_merge($data['body'], ['object' => $data['object']]),
      'queue' => $queue,
    ];
  }

  /**
   * 清空队列
   * @param string $queue 队列名称
   */
  public function purge($queue)
  {
    $this->redis->del([$queue, $queue . ':delayed', $queue . ':reserved']);
  }

  /**
   * 重新发布消息
   * @param array $message 消息数据
   * @param integer $delay 执行时间
   */
  public function release(array $message, $delay = 0)
  {
    if($delay > 0)
    {
      $this->redis->zadd($message['queue'] . ':delayed', [$message['body'] => time() + $delay]);
    }
    else
    {
      $this->redis->rpush($message['queue'], [$message['body']]);
    }
  }

  /**
   * 删除队列消息
   * @param array $message 删除的数据
   */
  public function delete(array $message)
  {
    $oldMessage = $message;
    unset($message['queue']);
    $object = $message['body']['object'];
    unset($message['body']['object']);
    $message['object'] = $object;
    $this->redis->zrem($oldMessage['queue'] . ':reserved', json_encode($message));
  }
}
