-- Esquema inicial de la plataforma d'inscripcions Pou de s'Horta

CREATE TABLE IF NOT EXISTS `settings` (
  `k` VARCHAR(120) NOT NULL,
  `v` LONGTEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('owner','admin','staff') NOT NULL DEFAULT 'admin',
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ticket_types` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(160) NOT NULL,
  `description` TEXT NULL,
  `includes` TEXT NULL,
  `price_cents` INT NOT NULL DEFAULT 0,
  `quota` INT NULL,
  `min_per_order` INT NOT NULL DEFAULT 0,
  `max_per_order` INT NOT NULL DEFAULT 10,
  `sales_start` DATETIME NULL,
  `sales_end` DATETIME NULL,
  `requires_attendee_name` TINYINT(1) NOT NULL DEFAULT 1,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tt_active` (`active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `form_fields` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_type_id` INT UNSIGNED NULL,
  `label` VARCHAR(160) NOT NULL,
  `slug` VARCHAR(80) NOT NULL,
  `type` ENUM('text','number','select','checkbox','textarea') NOT NULL DEFAULT 'text',
  `options` TEXT NULL,
  `required` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_ff_tt` (`ticket_type_id`, `sort_order`),
  CONSTRAINT `fk_ff_tt` FOREIGN KEY (`ticket_type_id`) REFERENCES `ticket_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference` VARCHAR(24) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `surname` VARCHAR(160) NULL,
  `phone` VARCHAR(40) NULL,
  `status` ENUM('pending','paid','cancelled','refunded','partially_refunded','failed','expired') NOT NULL DEFAULT 'pending',
  `subtotal_cents` INT NOT NULL DEFAULT 0,
  `fee_cents` INT NOT NULL DEFAULT 0,
  `total_cents` INT NOT NULL DEFAULT 0,
  `refunded_cents` INT NOT NULL DEFAULT 0,
  `currency` CHAR(3) NOT NULL DEFAULT 'EUR',
  `stripe_session_id` VARCHAR(255) NULL,
  `stripe_payment_intent` VARCHAR(255) NULL,
  `manage_token` CHAR(64) NOT NULL,
  `notes` TEXT NULL,
  `ip` VARCHAR(64) NULL,
  `user_agent` VARCHAR(255) NULL,
  `paid_at` DATETIME NULL,
  `cancelled_at` DATETIME NULL,
  `confirmation_sent_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_reference` (`reference`),
  KEY `idx_orders_email` (`email`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_created` (`created_at`),
  KEY `idx_orders_session` (`stripe_session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tickets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `ticket_type_id` INT UNSIGNED NOT NULL,
  `code` VARCHAR(32) NOT NULL,
  `attendee_name` VARCHAR(190) NULL,
  `price_cents` INT NOT NULL DEFAULT 0,
  `status` ENUM('valid','cancelled','refunded','used') NOT NULL DEFAULT 'valid',
  `extra_json` TEXT NULL,
  `checked_in_at` DATETIME NULL,
  `checked_in_by` VARCHAR(120) NULL,
  `cancelled_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tickets_code` (`code`),
  KEY `idx_tickets_order` (`order_id`),
  KEY `idx_tickets_type` (`ticket_type_id`),
  KEY `idx_tickets_status` (`status`),
  CONSTRAINT `fk_tickets_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tickets_tt` FOREIGN KEY (`ticket_type_id`) REFERENCES `ticket_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `refunds` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `amount_cents` INT NOT NULL,
  `stripe_refund_id` VARCHAR(255) NULL,
  `reason` VARCHAR(255) NULL,
  `initiated_by` VARCHAR(120) NOT NULL DEFAULT 'client',
  `status` VARCHAR(40) NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_refunds_order` (`order_id`),
  CONSTRAINT `fk_refunds_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `campaigns` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subject` VARCHAR(255) NOT NULL,
  `body` LONGTEXT NOT NULL,
  `filters_json` TEXT NULL,
  `status` ENUM('draft','queued','sending','sent','cancelled') NOT NULL DEFAULT 'draft',
  `total` INT NOT NULL DEFAULT 0,
  `sent_count` INT NOT NULL DEFAULT 0,
  `failed_count` INT NOT NULL DEFAULT 0,
  `created_by` VARCHAR(120) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` DATETIME NULL,
  `finished_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_queue` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campaign_id` INT UNSIGNED NULL,
  `to_email` VARCHAR(190) NOT NULL,
  `to_name` VARCHAR(190) NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body` LONGTEXT NOT NULL,
  `attachment_path` VARCHAR(255) NULL,
  `status` ENUM('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts` INT NOT NULL DEFAULT 0,
  `error` TEXT NULL,
  `available_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_eq_status` (`status`, `available_at`),
  KEY `idx_eq_campaign` (`campaign_id`),
  CONSTRAINT `fk_eq_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor` VARCHAR(120) NULL,
  `action` VARCHAR(120) NOT NULL,
  `target` VARCHAR(190) NULL,
  `details` TEXT NULL,
  `ip` VARCHAR(64) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `updates_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `from_version` VARCHAR(40) NULL,
  `to_version` VARCHAR(40) NULL,
  `strategy` VARCHAR(20) NOT NULL DEFAULT 'zip',
  `status` ENUM('running','success','failed','rolled_back') NOT NULL DEFAULT 'running',
  `output` LONGTEXT NULL,
  `backup_path` VARCHAR(255) NULL,
  `actor` VARCHAR(120) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `migrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `filename` VARCHAR(190) NOT NULL,
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_migrations_file` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `webhook_events` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `stripe_event_id` VARCHAR(190) NOT NULL,
  `type` VARCHAR(120) NOT NULL,
  `payload` LONGTEXT NULL,
  `processed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_we_event` (`stripe_event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `access_links` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token` CHAR(48) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `ip` VARCHAR(64) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_access_token` (`token`),
  KEY `idx_access_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
