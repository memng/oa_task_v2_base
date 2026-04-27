# ************************************************************
# Sequel Ace SQL dump
# 版本号： 20095
#
# https://sequel-ace.com/
# https://github.com/Sequel-Ace/Sequel-Ace
#
# 主机: 127.0.0.1 (MySQL 5.7.30-0ubuntu0.18.04.1)
# 数据库: oa_task_v2
# 生成时间: 2026-04-27 18:10:07 +0000
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
SET NAMES utf8mb4;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE='NO_AUTO_VALUE_ON_ZERO', SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# 转储表 admin_users
# ------------------------------------------------------------

DROP TABLE IF EXISTS `admin_users`;

CREATE TABLE `admin_users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL,
  `name` varchar(64) NOT NULL,
  `password` varchar(255) NOT NULL,
  `api_token` varchar(128) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 announcement_reads
# ------------------------------------------------------------

DROP TABLE IF EXISTS `announcement_reads`;

CREATE TABLE `announcement_reads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `announcement_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_announcement_read` (`announcement_id`,`user_id`),
  KEY `fk_announcement_reads_user` (`user_id`),
  CONSTRAINT `fk_announcement_reads_announcement` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_announcement_reads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 announcement_targets
# ------------------------------------------------------------

DROP TABLE IF EXISTS `announcement_targets`;

CREATE TABLE `announcement_targets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `announcement_id` bigint(20) unsigned NOT NULL,
  `dept_id` bigint(20) unsigned DEFAULT NULL,
  `role_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_announcement_targets_announcement` (`announcement_id`),
  KEY `idx_announcement_targets_dept` (`dept_id`),
  KEY `idx_announcement_targets_announcement_dept` (`announcement_id`,`dept_id`),
  CONSTRAINT `fk_announcement_targets_announcement` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 announcements
# ------------------------------------------------------------

DROP TABLE IF EXISTS `announcements`;

CREATE TABLE `announcements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `category` enum('factory','sales','general') DEFAULT 'general',
  `publish_status` enum('draft','published','archived') DEFAULT 'draft',
  `allow_comments` tinyint(1) DEFAULT '0',
  `published_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 app_cache
# ------------------------------------------------------------

DROP TABLE IF EXISTS `app_cache`;

CREATE TABLE `app_cache` (
  `cache_key` varchar(191) NOT NULL,
  `value` mediumtext,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`cache_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 attendance_records
# ------------------------------------------------------------

DROP TABLE IF EXISTS `attendance_records`;

CREATE TABLE `attendance_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rule_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `check_type` enum('check_in','check_out') DEFAULT 'check_in',
  `method` enum('wifi','gps','manual') DEFAULT 'gps',
  `lat` decimal(10,6) DEFAULT NULL,
  `lng` decimal(10,6) DEFAULT NULL,
  `wifi_ssid` varchar(64) DEFAULT NULL,
  `wifi_bssid` varchar(64) DEFAULT NULL,
  `photo_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('normal','late','early','absent') DEFAULT 'normal',
  `checked_at` datetime NOT NULL,
  `check_date` date DEFAULT NULL COMMENT '打卡日期',
  `remark` varchar(255) DEFAULT NULL,
  `location_text` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_attendance_records_user` (`user_id`),
  KEY `fk_attendance_records_rule` (`rule_id`),
  KEY `fk_attendance_records_photo` (`photo_id`),
  KEY `idx_attendance_records_check_date` (`check_date`),
  KEY `idx_attendance_records_user_date` (`user_id`,`check_date`),
  CONSTRAINT `fk_attendance_records_photo` FOREIGN KEY (`photo_id`) REFERENCES `media_assets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_attendance_records_rule` FOREIGN KEY (`rule_id`) REFERENCES `attendance_rules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_attendance_records_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 attendance_rules
# ------------------------------------------------------------

DROP TABLE IF EXISTS `attendance_rules`;

CREATE TABLE `attendance_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `dept_id` bigint(20) unsigned DEFAULT NULL,
  `workday` varchar(32) DEFAULT 'weekday',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `saturday_off` tinyint(1) NOT NULL DEFAULT '1' COMMENT '周六是否休息：0-上班，1-休息',
  `sunday_off` tinyint(1) NOT NULL DEFAULT '1' COMMENT '周日是否休息：0-上班，1-休息',
  `check_in_type` enum('wifi','gps','both') DEFAULT 'gps',
  `wifi_ssid` varchar(64) DEFAULT NULL,
  `wifi_bssid` varchar(64) DEFAULT NULL,
  `gps_lat` decimal(10,6) DEFAULT NULL,
  `gps_lng` decimal(10,6) DEFAULT NULL,
  `gps_radius` int(11) DEFAULT '200',
  `allow_late_minutes` int(11) DEFAULT '0',
  `allow_early_minutes` int(11) DEFAULT '0',
  `late_threshold_minutes` int(11) NOT NULL DEFAULT '30' COMMENT '迟到阈值（分钟），超过此时间算严重迟到',
  `early_threshold_minutes` int(11) NOT NULL DEFAULT '30' COMMENT '早退阈值（分钟），提前此时间下班算严重早退',
  `absent_after_minutes` int(11) NOT NULL DEFAULT '60' COMMENT '上班后多少分钟未打卡算旷工',
  `status` tinyint(1) DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `fk_attendance_rules_dept` (`dept_id`),
  CONSTRAINT `fk_attendance_rules_dept` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 audits
# ------------------------------------------------------------

DROP TABLE IF EXISTS `audits`;

CREATE TABLE `audits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint(20) unsigned DEFAULT NULL,
  `module` varchar(64) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `submitted_by` bigint(20) unsigned NOT NULL,
  `reviewer_id` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `comment` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audits_task` (`task_id`),
  CONSTRAINT `fk_audits_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 chat_members
# ------------------------------------------------------------

DROP TABLE IF EXISTS `chat_members`;

CREATE TABLE `chat_members` (
  `room_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `role` enum('owner','member') DEFAULT 'member',
  `last_read_message_id` bigint(20) unsigned DEFAULT NULL,
  `joined_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`room_id`,`user_id`),
  KEY `fk_chat_members_user` (`user_id`),
  CONSTRAINT `fk_chat_members_room` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 chat_messages
# ------------------------------------------------------------

DROP TABLE IF EXISTS `chat_messages`;

CREATE TABLE `chat_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `room_id` bigint(20) unsigned NOT NULL,
  `sender_id` bigint(20) unsigned NOT NULL,
  `message_type` enum('text','image','video','file','audio') DEFAULT 'text',
  `content` text,
  `media_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_messages_room` (`room_id`),
  KEY `idx_chat_messages_media` (`media_id`),
  CONSTRAINT `fk_chat_messages_media` FOREIGN KEY (`media_id`) REFERENCES `media_assets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_chat_messages_room` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 chat_rooms
# ------------------------------------------------------------

DROP TABLE IF EXISTS `chat_rooms`;

CREATE TABLE `chat_rooms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('direct','group') DEFAULT 'direct',
  `name` varchar(128) DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 currencies
# ------------------------------------------------------------

DROP TABLE IF EXISTS `currencies`;

CREATE TABLE `currencies` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `code` varchar(20) NOT NULL COMMENT '币种代码（如：USD、CNY）',
  `name` varchar(100) NOT NULL COMMENT '币种名称（如：美元、人民币）',
  `symbol` varchar(20) DEFAULT NULL COMMENT '符号（如：$、¥）',
  `sort_order` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `is_default` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否默认：0-否，1-是',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：0-禁用，1-启用',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_status_sort` (`status`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='币种表';



# 转储表 customer_factory_visits
# ------------------------------------------------------------

DROP TABLE IF EXISTS `customer_factory_visits`;

CREATE TABLE `customer_factory_visits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `requirements` text,
  `visit_date` datetime DEFAULT NULL,
  `assigned_to` bigint(20) unsigned NOT NULL,
  `feedback` text,
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customer_factory_visits_order` (`order_id`),
  KEY `fk_factory_visit_assigned` (`assigned_to`),
  KEY `fk_factory_visit_creator` (`created_by`),
  CONSTRAINT `fk_factory_visit_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_factory_visit_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_factory_visit_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 customers
# ------------------------------------------------------------

DROP TABLE IF EXISTS `customers`;

CREATE TABLE `customers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `contact_name` varchar(64) DEFAULT NULL,
  `contact_phone` varchar(32) DEFAULT NULL,
  `email` varchar(128) DEFAULT NULL,
  `country` varchar(64) DEFAULT NULL,
  `city` varchar(64) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 departments
# ------------------------------------------------------------

DROP TABLE IF EXISTS `departments`;

CREATE TABLE `departments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(64) NOT NULL,
  `code` varchar(32) DEFAULT NULL,
  `type` enum('sales','factory','finance','operation','other') DEFAULT 'other',
  `leader_user_id` bigint(20) unsigned DEFAULT NULL,
  `sort_order` int(11) DEFAULT '0',
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_departments_code` (`code`),
  KEY `idx_departments_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 expense_reports
# ------------------------------------------------------------

DROP TABLE IF EXISTS `expense_reports`;

CREATE TABLE `expense_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `type` varchar(64) NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `remark` text,
  `receipt_media_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approver_id` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_expense_reports_user` (`user_id`),
  KEY `fk_expense_reports_media` (`receipt_media_id`),
  KEY `fk_expense_reports_approver` (`approver_id`),
  CONSTRAINT `fk_expense_reports_approver` FOREIGN KEY (`approver_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_expense_reports_media` FOREIGN KEY (`receipt_media_id`) REFERENCES `media_assets` (`id`),
  CONSTRAINT `fk_expense_reports_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 intent_order_transitions
# ------------------------------------------------------------

DROP TABLE IF EXISTS `intent_order_transitions`;

CREATE TABLE `intent_order_transitions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `intent_order_id` bigint(20) unsigned NOT NULL COMMENT '意向单ID',
  `from_status` varchar(32) DEFAULT NULL COMMENT '原状态（新建时为NULL）',
  `to_status` varchar(32) NOT NULL COMMENT '新状态',
  `transition_type` enum('forward','backward','lost') NOT NULL COMMENT '流转类型：推进/回退/失败关闭',
  `reason` text COMMENT '流转原因（回退和失败关闭时必填）',
  `operator_id` bigint(20) unsigned NOT NULL COMMENT '操作人ID',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_intent_order_transitions_order` (`intent_order_id`),
  KEY `idx_intent_order_transitions_operator` (`operator_id`),
  CONSTRAINT `fk_intent_order_transitions_operator` FOREIGN KEY (`operator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_intent_order_transitions_order` FOREIGN KEY (`intent_order_id`) REFERENCES `intent_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='意向单阶段流转历史表';



# 转储表 intent_orders
# ------------------------------------------------------------

DROP TABLE IF EXISTS `intent_orders`;

CREATE TABLE `intent_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salesperson_id` bigint(20) unsigned NOT NULL,
  `customer_name` varchar(128) NOT NULL,
  `product_name` varchar(128) NOT NULL,
  `model` varchar(128) DEFAULT NULL,
  `voltage` varchar(64) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT '1',
  `customer_requirements` text,
  `status` enum('new','initial_review','requirement_confirm','proposal','business_negotiation','contract_review','won','lost') DEFAULT 'new' COMMENT '状态',
  `expected_close_date` date DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_intent_orders_sales` (`salesperson_id`),
  CONSTRAINT `fk_intent_orders_sales` FOREIGN KEY (`salesperson_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 leave_requests
# ------------------------------------------------------------

DROP TABLE IF EXISTS `leave_requests`;

CREATE TABLE `leave_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `leave_type` enum('annual','sick','personal','other') DEFAULT 'other',
  `start_at` datetime NOT NULL,
  `end_at` datetime NOT NULL,
  `duration_hours` decimal(6,2) NOT NULL,
  `reason` text,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `approver_id` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `attachment_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_leave_requests_user` (`user_id`),
  CONSTRAINT `fk_leave_requests_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 login_failure_records
# ------------------------------------------------------------

DROP TABLE IF EXISTS `login_failure_records`;

CREATE TABLE `login_failure_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mobile` varchar(20) NOT NULL COMMENT '手机号',
  `user_id` bigint(20) unsigned DEFAULT NULL COMMENT '用户ID（如果用户存在）',
  `failure_count` int(11) NOT NULL DEFAULT '0' COMMENT '连续失败次数',
  `last_failure_at` timestamp NULL DEFAULT NULL COMMENT '最后一次失败时间',
  `locked_until` timestamp NULL DEFAULT NULL COMMENT '锁定到何时',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mobile` (`mobile`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_locked_until` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='登录失败记录表';



# 转储表 media_assets
# ------------------------------------------------------------

DROP TABLE IF EXISTS `media_assets`;

CREATE TABLE `media_assets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) NOT NULL,
  `mime_type` varchar(64) DEFAULT NULL,
  `file_type` enum('image','video','audio','document','other') DEFAULT 'image',
  `storage_path` varchar(255) NOT NULL,
  `file_size` bigint(20) unsigned DEFAULT '0',
  `duration` int(11) DEFAULT NULL,
  `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `hash` varchar(64) DEFAULT NULL,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 member_stats
# ------------------------------------------------------------

DROP TABLE IF EXISTS `member_stats`;

CREATE TABLE `member_stats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `stat_date` date NOT NULL,
  `tasks_completed` int(11) DEFAULT '0',
  `orders_created` int(11) DEFAULT '0',
  `hours_worked` decimal(6,2) DEFAULT '0.00',
  `attendance_score` decimal(5,2) DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_member_stats` (`user_id`,`stat_date`),
  CONSTRAINT `fk_member_stats_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 notifications
# ------------------------------------------------------------

DROP TABLE IF EXISTS `notifications`;

CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `channel` enum('miniapp','service_account','email','sms','system') DEFAULT 'system',
  `template_code` varchar(64) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `content` text,
  `payload` json DEFAULT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 order_costs
# ------------------------------------------------------------

DROP TABLE IF EXISTS `order_costs`;

CREATE TABLE `order_costs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `order_product_id` bigint(20) unsigned DEFAULT NULL,
  `cost_scope` enum('domestic','international','finance') NOT NULL,
  `category` enum('domestic_freight','trailer','wood_case','warehouse','domestic_other','sea_freight','express','certificate','international_other','receipt_fee','usd_fee') NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(16) DEFAULT 'CNY',
  `description` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_costs_order` (`order_id`),
  KEY `idx_order_costs_order_product` (`order_product_id`),
  KEY `idx_order_costs_creator` (`created_by`),
  CONSTRAINT `fk_order_costs_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_order_costs_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_costs_product` FOREIGN KEY (`order_product_id`) REFERENCES `order_products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 order_documents
# ------------------------------------------------------------

DROP TABLE IF EXISTS `order_documents`;

CREATE TABLE `order_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `doc_type` enum('pi','commercial_invoice','customs_declaration','bill_of_lading','freight_invoice','payment_receipt') NOT NULL,
  `media_id` bigint(20) unsigned NOT NULL,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_documents` (`order_id`,`doc_type`),
  KEY `fk_order_documents_media` (`media_id`),
  KEY `fk_order_documents_uploader` (`uploaded_by`),
  CONSTRAINT `fk_order_documents_media` FOREIGN KEY (`media_id`) REFERENCES `media_assets` (`id`),
  CONSTRAINT `fk_order_documents_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_documents_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 order_products
# ------------------------------------------------------------

DROP TABLE IF EXISTS `order_products`;

CREATE TABLE `order_products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `product_name` varchar(128) NOT NULL,
  `model` varchar(128) DEFAULT NULL,
  `voltage` varchar(64) DEFAULT NULL,
  `power` varchar(64) DEFAULT NULL COMMENT '机器功率',
  `processing_length` varchar(128) DEFAULT NULL COMMENT '加工长度',
  `dimensions` varchar(128) DEFAULT NULL COMMENT '外形尺寸',
  `quantity` int(11) NOT NULL DEFAULT '1',
  `unit_price` decimal(12,2) DEFAULT '0.00',
  `total_price` decimal(12,2) DEFAULT NULL COMMENT '产品总价',
  `currency` varchar(16) DEFAULT 'CNY',
  `assignee_id` bigint(20) unsigned DEFAULT NULL COMMENT '采购人ID',
  `requirements` text,
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `idx_order_products_order` (`order_id`),
  KEY `idx_order_products_assignee` (`assignee_id`),
  CONSTRAINT `fk_order_products_assignee` FOREIGN KEY (`assignee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_order_products_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 orders
# ------------------------------------------------------------

DROP TABLE IF EXISTS `orders`;

CREATE TABLE `orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pi_number` varchar(64) NOT NULL,
  `pi_numbers` json DEFAULT NULL COMMENT '多个PI号JSON数组',
  `customer_id` bigint(20) unsigned DEFAULT NULL,
  `customer_name` varchar(128) NOT NULL,
  `status` enum('draft','in_progress','completed','cancelled') DEFAULT 'draft',
  `initiator_id` bigint(20) unsigned NOT NULL,
  `sales_owner_id` bigint(20) unsigned DEFAULT NULL,
  `currency` varchar(16) DEFAULT 'CNY',
  `delivery_period_days` int(11) DEFAULT NULL COMMENT '交货期天数',
  `expected_delivery_at` date DEFAULT NULL,
  `sea_freight` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '海运费',
  `discount_amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '折扣金额',
  `grand_total` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '订单总价',
  `requirement_text` text,
  `remark` text,
  `attachment_count` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_orders_pi` (`pi_number`),
  KEY `idx_orders_customer` (`customer_id`),
  KEY `fk_orders_initiator` (`initiator_id`),
  KEY `fk_orders_sales_owner` (`sales_owner_id`),
  CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_initiator` FOREIGN KEY (`initiator_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_orders_sales_owner` FOREIGN KEY (`sales_owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 permissions
# ------------------------------------------------------------

DROP TABLE IF EXISTS `permissions`;

CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(64) NOT NULL,
  `name` varchar(64) NOT NULL,
  `module` varchar(64) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_permissions_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 role_permissions
# ------------------------------------------------------------

DROP TABLE IF EXISTS `role_permissions`;

CREATE TABLE `role_permissions` (
  `role_id` bigint(20) unsigned NOT NULL,
  `permission_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `fk_role_permissions_permission` (`permission_id`),
  CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 roles
# ------------------------------------------------------------

DROP TABLE IF EXISTS `roles`;

CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `code` varchar(64) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `scope` enum('system','department','self') DEFAULT 'self',
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_roles_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 suppliers
# ------------------------------------------------------------

DROP TABLE IF EXISTS `suppliers`;

CREATE TABLE `suppliers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `contact_name` varchar(64) DEFAULT NULL,
  `contact_phone` varchar(32) DEFAULT NULL,
  `contact_email` varchar(128) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `payment_terms` varchar(128) DEFAULT NULL,
  `rating` tinyint(1) DEFAULT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT '0',
  `factory_owner_id` bigint(20) unsigned DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_supplier_factory_owner` (`factory_owner_id`),
  CONSTRAINT `fk_supplier_factory_owner` FOREIGN KEY (`factory_owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 system_settings
# ------------------------------------------------------------

DROP TABLE IF EXISTS `system_settings`;

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(64) NOT NULL,
  `setting_value` text,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_system_settings` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 task_acceptances
# ------------------------------------------------------------

DROP TABLE IF EXISTS `task_acceptances`;

CREATE TABLE `task_acceptances` (
  `task_id` bigint(20) unsigned NOT NULL,
  `requirement` text,
  `result` text,
  `score` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`task_id`),
  CONSTRAINT `fk_task_acceptance_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 task_attachments
# ------------------------------------------------------------

DROP TABLE IF EXISTS `task_attachments`;

CREATE TABLE `task_attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint(20) unsigned NOT NULL,
  `media_id` bigint(20) unsigned NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_task_attachments_task` (`task_id`),
  KEY `fk_task_attachments_media` (`media_id`),
  CONSTRAINT `fk_task_attachments_media` FOREIGN KEY (`media_id`) REFERENCES `media_assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_task_attachments_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 task_logs
# ------------------------------------------------------------

DROP TABLE IF EXISTS `task_logs`;

CREATE TABLE `task_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `action` varchar(64) NOT NULL,
  `message` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_task_logs_task` (`task_id`),
  CONSTRAINT `fk_task_logs_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 task_machine_data
# ------------------------------------------------------------

DROP TABLE IF EXISTS `task_machine_data`;

CREATE TABLE `task_machine_data` (
  `task_id` bigint(20) unsigned NOT NULL,
  `requirement` text,
  `data_photo_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`task_id`),
  KEY `fk_task_machine_data_media` (`data_photo_id`),
  CONSTRAINT `fk_task_machine_data_media` FOREIGN KEY (`data_photo_id`) REFERENCES `media_assets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_task_machine_data_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 task_nameplates
# ------------------------------------------------------------

DROP TABLE IF EXISTS `task_nameplates`;

CREATE TABLE `task_nameplates` (
  `task_id` bigint(20) unsigned NOT NULL,
  `template_version` varchar(32) DEFAULT NULL,
  `requirement` text,
  `printed_photo_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`task_id`),
  KEY `fk_task_nameplate_media` (`printed_photo_id`),
  CONSTRAINT `fk_task_nameplate_media` FOREIGN KEY (`printed_photo_id`) REFERENCES `media_assets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_task_nameplate_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 task_packaging
# ------------------------------------------------------------

DROP TABLE IF EXISTS `task_packaging`;

CREATE TABLE `task_packaging` (
  `task_id` bigint(20) unsigned NOT NULL,
  `requirement` text,
  `reviewer_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`task_id`),
  KEY `fk_task_packaging_reviewer` (`reviewer_id`),
  CONSTRAINT `fk_task_packaging_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_task_packaging_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 task_procurements
# ------------------------------------------------------------

DROP TABLE IF EXISTS `task_procurements`;

CREATE TABLE `task_procurements` (
  `task_id` bigint(20) unsigned NOT NULL,
  `supplier_id` bigint(20) unsigned DEFAULT NULL,
  `supplier_name` varchar(128) DEFAULT NULL,
  `purchase_status` enum('not_ordered','ordered','arrived') DEFAULT 'not_ordered',
  `purchase_date` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `source_location` varchar(128) DEFAULT NULL,
  `purchase_price` decimal(12,2) DEFAULT NULL,
  `currency` varchar(16) DEFAULT 'CNY',
  `is_confidential` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`task_id`),
  KEY `fk_task_procurement_supplier` (`supplier_id`),
  CONSTRAINT `fk_task_procurement_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_task_procurement_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 task_shipments
# ------------------------------------------------------------

DROP TABLE IF EXISTS `task_shipments`;

CREATE TABLE `task_shipments` (
  `task_id` bigint(20) unsigned NOT NULL,
  `requirement` text,
  `container_no` varchar(64) DEFAULT NULL,
  `seal_no` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`task_id`),
  CONSTRAINT `fk_task_shipment_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 tasks
# ------------------------------------------------------------

DROP TABLE IF EXISTS `tasks`;

CREATE TABLE `tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `order_product_id` bigint(20) unsigned DEFAULT NULL,
  `parent_task_id` bigint(20) unsigned DEFAULT NULL,
  `type` enum('procurement','nameplate','machine_data','acceptance','packaging','shipment','inspection','temporary','factory_order','fee','document','announcement','other') NOT NULL DEFAULT 'procurement',
  `title` varchar(255) NOT NULL,
  `description` text,
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `start_at` datetime DEFAULT NULL,
  `due_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `status` enum('pending','in_progress','waiting_audit','rejected','completed','cancelled') DEFAULT 'pending',
  `need_audit` tinyint(1) DEFAULT '0',
  `priority` tinyint(4) DEFAULT '3',
  `payload` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tasks_order` (`order_id`),
  KEY `idx_tasks_type` (`type`),
  KEY `idx_tasks_assigned` (`assigned_to`),
  KEY `fk_tasks_order_product` (`order_product_id`),
  KEY `fk_tasks_parent` (`parent_task_id`),
  CONSTRAINT `fk_tasks_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tasks_order_product` FOREIGN KEY (`order_product_id`) REFERENCES `order_products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tasks_parent` FOREIGN KEY (`parent_task_id`) REFERENCES `tasks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 user_roles
# ------------------------------------------------------------

DROP TABLE IF EXISTS `user_roles`;

CREATE TABLE `user_roles` (
  `user_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `fk_user_roles_role` (`role_id`),
  CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 users
# ------------------------------------------------------------

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dept_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(64) NOT NULL,
  `nickname` varchar(64) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `email` varchar(128) DEFAULT NULL,
  `id_card` varchar(32) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `bank_account_name` varchar(64) DEFAULT NULL,
  `bank_name` varchar(128) DEFAULT NULL,
  `bank_card_no` varchar(64) DEFAULT NULL,
  `openid` varchar(64) DEFAULT NULL,
  `unionid` varchar(64) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `api_token` varchar(128) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `status` enum('pending','active','disabled') DEFAULT 'pending',
  `hire_date` date DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_mobile` (`mobile`),
  UNIQUE KEY `uk_users_openid` (`openid`),
  UNIQUE KEY `uk_users_api_token` (`api_token`),
  KEY `fk_users_dept` (`dept_id`),
  CONSTRAINT `fk_users_dept` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# 转储表 voltages
# ------------------------------------------------------------

DROP TABLE IF EXISTS `voltages`;

CREATE TABLE `voltages` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `label` varchar(255) NOT NULL COMMENT '显示名称（如：220V/60Hz）',
  `value` varchar(100) NOT NULL COMMENT '实际值（用于提交）',
  `description` varchar(500) DEFAULT NULL COMMENT '描述',
  `sort_order` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：0-禁用，1-启用',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_value` (`value`),
  KEY `idx_status_sort` (`status`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='电压表';




/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
