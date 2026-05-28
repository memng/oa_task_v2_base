-- 订单阶段跟踪：为 orders 表添加 current_stage，创建阶段流转历史表，为 tasks 表添加延期原因

-- 1. 为 orders 表添加 current_stage 字段
SET @dbname = DATABASE();
SET @tablename = 'orders';
SET @colname = 'current_stage';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @colname) > 0,
  'SELECT 1',
  'ALTER TABLE `orders` ADD COLUMN `current_stage` varchar(32) DEFAULT NULL COMMENT ''当前阶段'' AFTER `status`'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. 创建订单阶段流转历史表
CREATE TABLE IF NOT EXISTS `order_stage_transitions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL COMMENT '订单ID',
  `from_stage` varchar(32) DEFAULT NULL COMMENT '原阶段（首次为NULL）',
  `to_stage` varchar(32) NOT NULL COMMENT '目标阶段',
  `transition_type` enum('forward','backward','skip') NOT NULL DEFAULT 'forward' COMMENT '流转类型',
  `delay_reason` text DEFAULT NULL COMMENT '延期原因（阶段超期时填写）',
  `is_overdue` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否超期',
  `operator_id` bigint(20) unsigned DEFAULT NULL COMMENT '操作人ID',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ost_order_id` (`order_id`),
  KEY `idx_ost_to_stage` (`to_stage`),
  KEY `idx_ost_created_at` (`created_at`),
  CONSTRAINT `fk_ost_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单阶段流转历史表';

-- 3. 为 tasks 表添加 delay_reason 及相关审计字段
SET @tablename = 'tasks';
SET @colname = 'delay_reason';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @colname) > 0,
  'SELECT 1',
  'ALTER TABLE `tasks` ADD COLUMN `delay_reason` text DEFAULT NULL COMMENT ''延期原因'' AFTER `completed_at`'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @colname = 'delay_reason_updated_at';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @colname) > 0,
  'SELECT 1',
  'ALTER TABLE `tasks` ADD COLUMN `delay_reason_updated_at` datetime DEFAULT NULL COMMENT ''延期原因更新时间'' AFTER `delay_reason`'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @colname = 'delay_reason_updated_by';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @colname) > 0,
  'SELECT 1',
  'ALTER TABLE `tasks` ADD COLUMN `delay_reason_updated_by` bigint(20) unsigned DEFAULT NULL COMMENT ''延期原因更新人ID'' AFTER `delay_reason_updated_at`,
   ADD KEY `idx_tasks_delay_reason_updated_by` (`delay_reason_updated_by`)'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. 为现有订单根据任务完成情况回填 current_stage
-- 4a. 有任务且所有阶段任务都完成的订单：current_stage = NULL（表示流程已全部完成）
UPDATE `orders` o
SET o.current_stage = NULL
WHERE o.current_stage IS NULL
  AND o.status != 'draft'
  AND o.status != 'cancelled'
  AND o.status = 'completed'
  AND EXISTS (SELECT 1 FROM `tasks` t WHERE t.order_id = o.id AND t.status NOT IN ('cancelled'));

-- 4b. 有任务但仍有未完成的订单：找到第一个还有未完成任务的阶段
UPDATE `orders` o
SET o.current_stage = (
  SELECT CASE
    WHEN EXISTS (SELECT 1 FROM `tasks` t WHERE t.order_id = o.id AND t.type IN ('procurement','factory_order') AND t.status NOT IN ('completed','cancelled')) THEN 'procurement'
    WHEN EXISTS (SELECT 1 FROM `tasks` t WHERE t.order_id = o.id AND t.type = 'nameplate' AND t.status NOT IN ('completed','cancelled')) THEN 'nameplate'
    WHEN EXISTS (SELECT 1 FROM `tasks` t WHERE t.order_id = o.id AND t.type = 'machine_data' AND t.status NOT IN ('completed','cancelled')) THEN 'machine_data'
    WHEN EXISTS (SELECT 1 FROM `tasks` t WHERE t.order_id = o.id AND t.type = 'acceptance' AND t.status NOT IN ('completed','cancelled')) THEN 'acceptance'
    WHEN EXISTS (SELECT 1 FROM `tasks` t WHERE t.order_id = o.id AND t.type = 'packaging' AND t.status NOT IN ('completed','cancelled')) THEN 'packaging'
    WHEN EXISTS (SELECT 1 FROM `tasks` t WHERE t.order_id = o.id AND t.type = 'shipment' AND t.status NOT IN ('completed','cancelled')) THEN 'shipment'
    ELSE NULL
  END
)
WHERE o.current_stage IS NULL
  AND o.status != 'draft'
  AND o.status != 'cancelled'
  AND o.status != 'completed'
  AND EXISTS (SELECT 1 FROM `tasks` t WHERE t.order_id = o.id AND t.status NOT IN ('cancelled'));

-- 4c. 无任务的非草稿订单：current_stage = 'procurement'（标记为初始阶段，等待创建任务）
UPDATE `orders` o
SET o.current_stage = 'procurement'
WHERE o.current_stage IS NULL
  AND o.status != 'draft'
  AND o.status != 'cancelled'
  AND NOT EXISTS (SELECT 1 FROM `tasks` t WHERE t.order_id = o.id AND t.status NOT IN ('cancelled'));
