CREATE TABLE IF NOT EXISTS `expense_budgets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dept_id` bigint(20) unsigned DEFAULT NULL COMMENT '部门ID，NULL表示全局预算',
  `type` varchar(64) NOT NULL DEFAULT '' COMMENT '报销类型，空字符串表示所有类型',
  `period` varchar(7) NOT NULL COMMENT '预算周期，格式 YYYY-MM',
  `budget_amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '预算金额',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_budget_dept_type_period` (`dept_id`, `type`, `period`),
  KEY `idx_budget_period` (`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='报销预算表';
