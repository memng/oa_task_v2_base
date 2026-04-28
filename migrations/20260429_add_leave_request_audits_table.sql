-- 为请假申请添加撤回功能和审计日志支持
-- 1. 确保 leave_requests 表的 status 字段包含 cancelled 状态（已存在）
-- 2. 创建 leave_request_audits 表用于记录状态变更历史（包括撤回）

-- 创建请假申请审计日志表
CREATE TABLE IF NOT EXISTS `leave_request_audits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `leave_request_id` bigint(20) unsigned NOT NULL COMMENT '请假申请ID',
  `action` varchar(32) NOT NULL COMMENT '操作类型：create, submit, approve, reject, cancel',
  `from_status` varchar(32) DEFAULT NULL COMMENT '原状态（新建时为NULL）',
  `to_status` varchar(32) NOT NULL COMMENT '新状态',
  `operator_id` bigint(20) unsigned NOT NULL COMMENT '操作人ID',
  `reason` text COMMENT '操作原因（撤回和驳回时必填）',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_leave_request_audits_request` (`leave_request_id`),
  KEY `idx_leave_request_audits_operator` (`operator_id`),
  CONSTRAINT `fk_leave_request_audits_request` FOREIGN KEY (`leave_request_id`) REFERENCES `leave_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_leave_request_audits_operator` FOREIGN KEY (`operator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='请假申请状态变更历史表';
