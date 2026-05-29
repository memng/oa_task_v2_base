-- 聊天会话置顶、免打扰、未读清零功能
-- 添加字段到 chat_members 表

ALTER TABLE `chat_members`
ADD COLUMN `is_pinned` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否置顶：0-否，1-是' AFTER `last_read_message_id`,
ADD COLUMN `is_muted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否免打扰：0-否，1-是' AFTER `is_pinned`,
ADD COLUMN `pinned_at` DATETIME NULL DEFAULT NULL COMMENT '置顶时间' AFTER `is_muted`;

-- 添加索引
ALTER TABLE `chat_members`
ADD INDEX `idx_user_pinned` (`user_id`, `is_pinned`, `pinned_at` DESC);
