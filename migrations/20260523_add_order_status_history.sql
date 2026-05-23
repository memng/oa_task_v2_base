-- 订单状态变更历史表
-- 用于看板按阶段流转时间统计订单趋势

-- 1. 建表（新环境）
CREATE TABLE IF NOT EXISTS `order_status_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `old_status` varchar(32) DEFAULT NULL COMMENT '变更前状态，初始为空',
  `new_status` varchar(32) NOT NULL COMMENT '变更后状态',
  `changed_by` bigint(20) unsigned DEFAULT NULL COMMENT '操作人用户ID',
  `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '状态变更时间',
  `currency_snapshot` varchar(16) DEFAULT NULL COMMENT '变更时订单币种快照',
  `grand_total_snapshot` decimal(12,2) DEFAULT NULL COMMENT '变更时订单金额快照',
  PRIMARY KEY (`id`),
  KEY `idx_osh_order_id` (`order_id`),
  KEY `idx_osh_new_status` (`new_status`),
  KEY `idx_osh_changed_at` (`changed_at`),
  KEY `idx_osh_order_status` (`order_id`, `new_status`, `changed_at`),
  -- 幂等约束：同订单同 old->new 在同一秒内只允许一次
  UNIQUE KEY `uk_osh_idempotent` (`order_id`, `old_status`, `new_status`, `changed_at`),
  CONSTRAINT `fk_osh_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单状态变更历史';

-- 2. 增量兼容：已有表补列（线上已存在 order_status_history 但无快照列时）
SET @dbname = DATABASE();
SET @tablename = 'order_status_history';

SET @colname = 'currency_snapshot';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @colname) > 0,
  'SELECT 1',
  'ALTER TABLE `order_status_history` ADD COLUMN `currency_snapshot` varchar(16) DEFAULT NULL COMMENT ''变更时订单币种快照'''
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @colname = 'grand_total_snapshot';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @colname) > 0,
  'SELECT 1',
  'ALTER TABLE `order_status_history` ADD COLUMN `grand_total_snapshot` decimal(12,2) DEFAULT NULL COMMENT ''变更时订单金额快照'''
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. 增量兼容：已有表补唯一索引
SET @indexname = 'uk_osh_idempotent';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = @indexname) > 0,
  'SELECT 1',
  'ALTER TABLE `order_status_history` ADD UNIQUE KEY `uk_osh_idempotent` (`order_id`, `old_status`, `new_status`, `changed_at`)'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. 为现有订单补齐初始状态记录（以 created_at 作为变更时间，快照当前币种/金额）
INSERT INTO `order_status_history` (`order_id`, `old_status`, `new_status`, `changed_by`, `changed_at`, `currency_snapshot`, `grand_total_snapshot`)
SELECT o.id, NULL, o.status, o.initiator_id, o.created_at, o.currency, o.grand_total
FROM `orders` o
WHERE NOT EXISTS (
  SELECT 1 FROM `order_status_history` h WHERE h.order_id = o.id
);
