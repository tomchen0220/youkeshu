<?php

namespace queue;

/**
 * Interface QueueInterface
 * @package queue
 * @author longli
 */
interface QueueInterface
{
    /**
     * 添加数据到队列
     * @param mixed $payload 队列对象
     * @param string $queue 队列名称
     * @param integer $delay 执行时间
     * @return string
     */
    public function push(ActiveJob $payload, $queue = null, $delay = 0);

    /**
     * 从队列中出栈
     * @param string $queue 队列名称
     * @return array|false
     */
    public function pop($queue);

    /**
     * 清空队列
     * @param string $queue 队列名称
     */
    public function purge($queue);

    /**
     * 重新发布消息
     * @param array $message 消息数据
     * @param integer $delay 执行时间
     */
    public function release(array $message, $delay = 0);

    /**
     * 删除队列消息
     * @param array $message 删除的数据
     */
    public function delete(array $message);
}