-- 请假撤回功能增强：添加撤回原因字段
-- 1. leave_requests 增加 cancel_reason 字段存储整条流程的撤回说明
-- 2. leave_approval_flows 的 reason 字段扩展长度以支持存储撤回原因

ALTER TABLE `leave_requests`
ADD COLUMN `cancel_reason` VARCHAR(500) DEFAULT NULL COMMENT '撤回原因' AFTER `reason`;
