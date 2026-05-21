-- 创建任务模板表，用于存储常用任务模板以支持快速复用
CREATE TABLE IF NOT EXISTS `task_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '模板名称',
  `type` varchar(32) NOT NULL DEFAULT 'procurement' COMMENT '任务类型',
  `title` varchar(150) NOT NULL COMMENT '任务标题',
  `description` text COMMENT '任务说明',
  `assigned_to` bigint(20) unsigned DEFAULT NULL COMMENT '默认负责人ID',
  `need_audit` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否需要审核',
  `extra` text COMMENT '扩展字段JSON（如采购、铭牌等额外字段）',
  `created_by` bigint(20) unsigned NOT NULL COMMENT '创建人ID',
  `is_global` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否为全局模板（所有用户可见）',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_task_templates_creator` (`created_by`),
  KEY `idx_task_templates_global` (`is_global`),
  KEY `idx_task_templates_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='任务模板表';
