-- 为 tasks 表添加 tags 字段用于标签系统
-- 标签类型：紧急(urgent)、客户(customer)、内部(internal)

ALTER TABLE `tasks` 
ADD COLUMN `tags` JSON DEFAULT NULL COMMENT '任务标签，JSON数组格式：["urgent","customer"]' 
AFTER `priority`;

-- 添加索引
ALTER TABLE `tasks` ADD INDEX `idx_tasks_priority` (`priority`);
