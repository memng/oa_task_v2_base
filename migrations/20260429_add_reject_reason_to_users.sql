-- 为 users 表添加注册审核拒绝原因字段
-- 用于后台审核拒绝时记录原因，用户登录时可查看

-- 1. 添加 reject_reason 字段
ALTER TABLE `users` 
ADD COLUMN `reject_reason` VARCHAR(500) NULL COMMENT '注册审核拒绝原因' 
AFTER `status`;
