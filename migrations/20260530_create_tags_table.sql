-- 创建标签配置表
CREATE TABLE IF NOT EXISTS `tags` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '标签ID',
  `key` VARCHAR(50) NOT NULL COMMENT '标签键名（英文标识）',
  `label` VARCHAR(50) NOT NULL COMMENT '标签显示名称',
  `color` VARCHAR(7) NOT NULL DEFAULT '#1677ff' COMMENT '标签颜色（HEX格式）',
  `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序，数字越小越靠前',
  `is_default` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否系统默认标签：0-否，1-是',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tags_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='任务标签配置表';

-- 插入默认标签
INSERT IGNORE INTO `tags` (`key`, `label`, `color`, `sort`, `is_default`) VALUES
('urgent', '紧急', '#ff4d4f', 1, 1),
('customer', '客户', '#1677ff', 2, 1),
('internal', '内部', '#722ed1', 3, 1);
