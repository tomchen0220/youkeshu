<?php

namespace queue\console;
use queue\Tools;

/**
 * Class QueueListener
 * @package queue\console
 * @author longli
 */
class QueueListener
{
  /**
   * @var integer
   * 进程睡眠多少毫秒
   */
  public $sleep = 100;

  /**
   * @var integer
   * 执行超时时间，秒
   */
  public $_timeout;

  /**
   * 重新发布失败
   * @var bool
   */
  public $restartOnFailure = true;

  /**
   * 队列名称
   * @var string
   */
  public $queue = 'default_queue';

  /**
   * 进程数，windows只能用单进程
   * @var int
   */
  public $workerNum = 0;


  public function __construct()
  {
    $options = getopt('', ['queue::', 'worker::']);
    $this->queue = !isset($options['queue']) ? self::getDefaultQueueName() : $options['queue'];
    if(isset($options['worker']) && $options['worker'] > 0 && $options['worker'] < 21)
      $this->workerNum = intval($options['worker']);
    $this->listen($this->queue);
  }

  /**
   * 执行队列一次
   * @param string $queue 队列名称
   * @throws \Exception
   */
  public function work($queue)
  {
    $this->process($queue);
  }

  /**
   * 监听队列
   * @param string $queue 队列名称
   * @return bool
   * @throws \Exception
   */
  public function listen($queue)
  {
    if(class_exists('\swoole_process'))
    {
      $this->sProcess($queue);
    }
    else
    {
      while(true)
      {
        if(!$this->process($queue))
        {
          usleep($this->sleep);
        }
      }
    }
  }

  /**
   * 队列进程
   * @param string $queue 队列名称
   * @return bool
   */
  protected function process($queue)
  {
    $message = $this->getQueue()->pop($queue);
    if($message)
    {
      try
      {
        /** @var \queue\ActiveJob $job */
        $job = call_user_func($message['body']['serializer'][1], $message['body']['object']);

        if($job->run() || (bool)$this->restartOnFailure === false)
        {
          $this->getQueue()->delete($message);
        }
        else
        {
          $this->getQueue()->release($message, 60);
        }
        return true;
      }
      catch(\Exception $e)
      {
        $this->getQueue()->delete($message);
      }
    }
    return false;
  }

  /**
   * 进程池
   * @var array
   */
  private $workers = [];

  /**
   * 使用 swoole 运行多进程
   * @param string $queuqName 队列名称
   */
  protected function sProcess($queuqName)
  {
    ini_set('memory_limit', '1024M');
    if(empty($this->workerNum))
    {
      $config = self::getConfig();
      $this->workerNum = isset($config['queue']['num']) && $config['queue']['num'] > 0
      && $config['queue']['num'] < 21 ? $config['queue']['num'] : 5;
    }
    while($this->workerNum--)
    {
      $process = new \swoole_process(function(\swoole_process $process) use ($queuqName)
      {
        while(true)
        {
          usleep($this->sleep);
          $this->process($queuqName);
        }
      });
      $pid = $process->start();
      $this->workers[$pid] = $process;
    }
    while(true)
    {
      $ret = \swoole_process::wait();
      if($ret)
      {
        $pid = $ret['pid'];
        $worker = $this->workers[$pid];
        $npid = $worker->start();
        $this->workers[$npid] = $worker;
        unset($this->workers[$pid]);
      }
    }
  }

  /**
   * 获取队列驱动
   * @return \queue\QueueInterface
   */
  private function getQueue()
  {
    return Tools::getQueue();
  }

  /**
   * 获取队列默认名称
   * @return string
   */
  public static function getDefaultQueueName()
  {
    $config = self::getConfig();
    return $config['queue']['queueName'];
  }

  /**
   * 获取配置文件
   * @return array
   */
  public static function getConfig()
  {
    return require(dirname(__DIR__) . '/config.php');
  }
}

?>
