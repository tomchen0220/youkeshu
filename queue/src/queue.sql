CREATE TABLE `sys_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '队列id',
  `queue` varchar(255) NOT NULL COMMENT '队列名称',
  `attempts` smallint(5) NOT NULL DEFAULT '1' COMMENT '尝试次数',
  `payload` longtext NOT NULL COMMENT '数据',
  `reserved` tinyint(1) DEFAULT '0' COMMENT '是否保留',
  `reserved_at` int(11) DEFAULT NULL COMMENT '保留时间',
  `available_at` int(11) DEFAULT NULL COMMENT '可以获取的时间',
  `created_at` int(11) DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
