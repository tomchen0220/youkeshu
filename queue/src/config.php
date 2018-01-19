<?php


return $__CONFIG = [
  'queue' => [
    'class' => 'db',  // 使用哪个驱动
    'queueName' => 'default_queue',  // 队列名称
    'num' => 5, // 跑多少个进程，默认5个,可选值(1-20)
    'attempts' => 5, //最多执行次数
  ],
  'redis' => [
    'scheme' => 'tcp',
    'host' => '127.0.0.1',
    'port' => 6379,
    //'password' => '123456',
    'db' => 0,
  ],
  //数据库连接池
  'db' => [
    'default' => [
      //数据库连接参数
      'params' => [
        'host'       => '120.79.0.214',  // 数据库连接地址
        'port'       => 3306, // 端口
        'user'       => 'weichen',  // 用户名
        'password'   => '15112699412cw', // 密码
        'database'   => 'elegomallerpssz',  // 数据库名
        'charset'    => 'utf8',  // 编码
        'tableName' => 'yii2_sys_queue'  // 队列表名
      ]
    ],
  ],
];