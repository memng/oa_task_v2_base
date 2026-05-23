-- 任务关注表：记录用户关注的任务，支持在任务发生变更时通知关注者
CREATE TABLE IF NOT EXISTS `task_followers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint(20) unsigned NOT NULL COMMENT '任务ID',
  `user_id` bigint(20) unsigned NOT NULL COMMENT '关注人ID',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_task_user` (`task_id`, `user_id`),
  KEY `idx_task_followers_user` (`user_id`),
  CONSTRAINT `fk_task_followers_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_task_followers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='任务关注关系表';
