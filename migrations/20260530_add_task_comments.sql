-- 任务评论表：支持@成员和附件
CREATE TABLE IF NOT EXISTS `task_comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint(20) unsigned NOT NULL COMMENT '任务ID',
  `user_id` bigint(20) unsigned NOT NULL COMMENT '评论人ID',
  `content` text NOT NULL COMMENT '评论内容',
  `mentions` json DEFAULT NULL COMMENT '@的用户ID列表',
  `reply_to` bigint(20) unsigned DEFAULT NULL COMMENT '回复的评论ID',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_task_comments_task` (`task_id`),
  KEY `idx_task_comments_user` (`user_id`),
  KEY `idx_task_comments_reply` (`reply_to`),
  CONSTRAINT `fk_task_comments_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_task_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_task_comments_reply_to` FOREIGN KEY (`reply_to`) REFERENCES `task_comments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='任务评论表';

-- 任务评论附件表
CREATE TABLE IF NOT EXISTS `task_comment_attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `comment_id` bigint(20) unsigned NOT NULL COMMENT '评论ID',
  `media_id` bigint(20) unsigned NOT NULL COMMENT '媒体资源ID',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_comment_attachments_comment` (`comment_id`),
  KEY `idx_comment_attachments_media` (`media_id`),
  CONSTRAINT `fk_comment_attachments_comment` FOREIGN KEY (`comment_id`) REFERENCES `task_comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comment_attachments_media` FOREIGN KEY (`media_id`) REFERENCES `media_assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='任务评论附件表';
