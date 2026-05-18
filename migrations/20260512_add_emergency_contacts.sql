-- 添加紧急联系人表
CREATE TABLE IF NOT EXISTS `emergency_contacts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `name` varchar(64) NOT NULL COMMENT '联系人姓名',
  `mobile` varchar(20) NOT NULL COMMENT '联系人手机号',
  `relationship` varchar(32) DEFAULT NULL COMMENT '与本人关系',
  `is_primary` tinyint(1) DEFAULT '0' COMMENT '是否为主要联系人 0-否 1-是',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_emergency_contacts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='紧急联系人表';

-- 添加验证码表用于手机号换绑
CREATE TABLE IF NOT EXISTS `verification_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mobile` varchar(20) NOT NULL COMMENT '手机号',
  `code` varchar(6) NOT NULL COMMENT '验证码',
  `type` varchar(32) NOT NULL COMMENT '验证码类型：change_mobile 等',
  `expired_at` datetime NOT NULL COMMENT '过期时间',
  `used_at` datetime DEFAULT NULL COMMENT '使用时间',
  `verify_error_count` int(11) DEFAULT '0' COMMENT '验证码错误次数',
  `locked_until` datetime DEFAULT NULL COMMENT '锁定截止时间（错误次数过多时锁定）',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mobile_type` (`mobile`, `type`),
  KEY `idx_expired_at` (`expired_at`),
  KEY `idx_locked_until` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='验证码表';

-- 允许用户资料更新时修改手机号（但需要通过专门的换绑接口）
-- 扩展 users 表状态枚举，添加 rejected 状态（如果还没有）
-- 注意：原表已有 status 字段，但只有 pending,active,disabled
