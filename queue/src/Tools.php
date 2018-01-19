<?php

namespace queue;

/**
 * Class Tools
 * @package queue
 * @author longli
 */
class Tools
{
  /**
   * 获取队列
   * @return QueueInterface
   * @throws \Exception
   */
  public static function getQueue()
  {
    $config = require(__DIR__ . '/config.php');
    switch($config['queue']['class'])
    {
      case 'db':
        return (new DbQueue());
      case 'redis';
        return (new RedisQueue());
      default:
        throw new \Exception("配置有误");
    }
  }

  /**
   * 获取队列配置文件
   * @return array
   * @author longli
   */
  public static function getConfig()
  {
    return require(__DIR__ . '/config.php');
  }
}
