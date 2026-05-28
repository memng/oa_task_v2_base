-- 请假审批流：支持按部门/级别动态分配审批人
-- 1. 创建审批规则表
-- 2. 创建审批步骤表
-- 3. 创建审批流程记录表
-- 4. leave_requests 增加当前审批步骤字段
-- 5. 增加 skip_reason 字段区分自动通过和异常跳过
-- 6. 状态枚举增加 auto_skipped
-- 7. 老数据兼容：将历史 role 类型迁移为 dept_leader

CREATE TABLE IF NOT EXISTS `leave_approval_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL COMMENT '规则名称',
  `dept_id` bigint(20) unsigned DEFAULT NULL COMMENT '适用部门ID，NULL表示全部部门',
  `level_min` int(11) DEFAULT NULL COMMENT '适用最低职级，NULL表示不限',
  `level_max` int(11) DEFAULT NULL COMMENT '适用最高职级，NULL表示不限',
  `leave_type` varchar(32) DEFAULT NULL COMMENT '适用请假类型，NULL表示全部类型',
  `priority` int(11) NOT NULL DEFAULT '0' COMMENT '优先级，数值越大越优先匹配',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1启用 0禁用',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_leave_approval_rules_dept` (`dept_id`),
  KEY `idx_leave_approval_rules_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='请假审批规则表';

CREATE TABLE IF NOT EXISTS `leave_approval_steps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rule_id` bigint(20) unsigned NOT NULL COMMENT '所属审批规则ID',
  `step_order` int(11) NOT NULL COMMENT '步骤顺序，从1开始',
  `step_name` varchar(64) NOT NULL COMMENT '步骤名称，如：部门主管审批',
  `approver_type` enum('dept_leader','specific_user','level_up') NOT NULL DEFAULT 'dept_leader' COMMENT '审批人类型：dept_leader=部门主管,specific_user=指定用户,level_up=直属上级',
  `approver_user_id` bigint(20) unsigned DEFAULT NULL COMMENT '指定审批人用户ID（approver_type=specific_user时）',
  `auto_approve` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否自动通过（无审批人时自动跳过，非自动的步骤无审批人将阻断提交）',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_leave_approval_steps_rule` (`rule_id`),
  CONSTRAINT `fk_leave_approval_steps_rule` FOREIGN KEY (`rule_id`) REFERENCES `leave_approval_rules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='请假审批步骤表';

CREATE TABLE IF NOT EXISTS `leave_approval_flows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `leave_request_id` bigint(20) unsigned NOT NULL COMMENT '请假申请ID',
  `step_order` int(11) NOT NULL COMMENT '步骤顺序',
  `step_name` varchar(64) NOT NULL COMMENT '步骤名称',
  `approver_type` varchar(32) NOT NULL COMMENT '审批人类型',
  `approver_user_id` bigint(20) unsigned DEFAULT NULL COMMENT '审批人用户ID',
  `status` enum('pending','approved','rejected','auto_skipped') NOT NULL DEFAULT 'pending' COMMENT '步骤状态：pending待审批,approved通过,rejected拒绝,auto_skipped配置自动通过',
  `skip_reason` varchar(255) DEFAULT NULL COMMENT '跳过原因：auto_approve=配置为自动通过',
  `approved_at` timestamp NULL DEFAULT NULL COMMENT '审批时间',
  `reason` text COMMENT '审批备注',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_leave_approval_flows_request` (`leave_request_id`),
  KEY `idx_leave_approval_flows_approver` (`approver_user_id`),
  KEY `idx_leave_approval_flows_status` (`status`),
  CONSTRAINT `fk_leave_approval_flows_request` FOREIGN KEY (`leave_request_id`) REFERENCES `leave_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='请假审批流程记录表';

ALTER TABLE `leave_requests` ADD COLUMN `current_step` int(11) DEFAULT NULL COMMENT '当前审批步骤' AFTER `status`;
ALTER TABLE `leave_requests` ADD COLUMN `rule_id` bigint(20) unsigned DEFAULT NULL COMMENT '匹配的审批规则ID' AFTER `current_step`;

ALTER TABLE `users` ADD COLUMN `level` int(11) DEFAULT NULL COMMENT '职级' AFTER `hire_date`;
ALTER TABLE `users` ADD COLUMN `manager_id` bigint(20) unsigned DEFAULT NULL COMMENT '直属上级用户ID' AFTER `level`;

-- 老数据兼容：如果历史 leave_approval_flows 中存在 status='skipped' 的记录，
-- 需要根据 skip_reason 区分或统一迁移为 auto_skipped
UPDATE `leave_approval_flows` SET `status` = 'auto_skipped', `skip_reason` = 'auto_approve' WHERE `status` = 'skipped';

-- 老数据兼容：如果历史 leave_approval_steps 或 leave_approval_flows 中存在 approver_type='role'，
-- 先将 leave_approval_steps 中的 role 迁移为 dept_leader（需临时修改枚举）
ALTER TABLE `leave_approval_steps` MODIFY COLUMN `approver_type` varchar(32) NOT NULL DEFAULT 'dept_leader' COMMENT '审批人类型：dept_leader=部门主管,specific_user=指定用户,level_up=直属上级';
UPDATE `leave_approval_steps` SET `approver_type` = 'dept_leader' WHERE `approver_type` = 'role';
ALTER TABLE `leave_approval_steps` MODIFY COLUMN `approver_type` enum('dept_leader','specific_user','level_up') NOT NULL DEFAULT 'dept_leader' COMMENT '审批人类型：dept_leader=部门主管,specific_user=指定用户,level_up=直属上级';

UPDATE `leave_approval_flows` SET `approver_type` = 'dept_leader' WHERE `approver_type` = 'role';
