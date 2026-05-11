-- 驳回原因模板表
CREATE TABLE `reject_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `content` varchar(500) NOT NULL COMMENT '模板内容',
  `sort_order` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reject_templates_active` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='驳回原因模板表';

-- 插入默认模板
INSERT INTO `reject_templates` (`content`, `sort_order`) VALUES
('身份证号码格式不正确，请核对后重新填写', 1),
('银行卡号格式不正确，请核对后重新填写', 2),
('所选部门已停用，请选择其他部门', 3),
('手机号已被注册，请使用其他手机号', 4),
('请补充完整的资料信息', 5);

-- 为 users 表添加重新提交状态支持
-- 将 status 枚举扩展，增加 'rejected' 状态
-- 注意：MySQL 不支持直接修改 enum 顺序，需要重建列

ALTER TABLE `users` MODIFY COLUMN `status` enum('pending','active','disabled','rejected') DEFAULT 'pending';

-- 更新现有 disabled 记录（如果是被驳回的），此处保持兼容，不强制迁移历史数据
-- 后续驳回操作优先使用 'rejected' 状态
