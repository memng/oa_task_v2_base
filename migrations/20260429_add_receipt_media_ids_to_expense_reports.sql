-- 为 expense_reports 表添加多附件支持字段
-- 此迁移将旧的单附件字段 receipt_media_id 升级为多附件字段 receipt_media_ids

-- 1. 添加新的 JSON 字段 receipt_media_ids
ALTER TABLE `expense_reports` 
ADD COLUMN `receipt_media_ids` JSON NULL COMMENT '票据附件ID列表（JSON数组，支持多附件）' 
AFTER `receipt_media_id`;

-- 2. 迁移现有数据：将旧的 receipt_media_id 转换为 receipt_media_ids 数组
-- 注意：此语句需要 MySQL 5.7+ 支持
UPDATE `expense_reports` 
SET `receipt_media_ids` = JSON_ARRAY(`receipt_media_id`) 
WHERE `receipt_media_id` IS NOT NULL AND `receipt_media_ids` IS NULL;

-- 3. （可选）删除外键约束（如果需要完全移除旧字段）
-- 先查看外键约束名：
-- SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
-- WHERE TABLE_NAME = 'expense_reports' AND COLUMN_NAME = 'receipt_media_id';

-- 然后删除外键约束：
-- ALTER TABLE `expense_reports` DROP FOREIGN KEY `fk_expense_reports_media`;

-- 4. （可选）删除旧字段 receipt_media_id（建议保留一段时间用于兼容）
-- ALTER TABLE `expense_reports` DROP COLUMN `receipt_media_id`;
