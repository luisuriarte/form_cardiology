-- =============================================================================
-- Cardiology Form Module: Upgrade 002 - Settings tool card
-- Location: interface/forms/form_cardiology/sql/upgrade_002_settings.sql
--
-- Adds:
--   1. card_modules.card_type with values study or tool
--   2. card_settings table for clinic and per-user preferences
--   3. The Settings tool card in card_modules
--
-- Safe to re-run on existing databases (idempotent DDL and seeds).
-- Compatible with OpenEMR 8.2.0 - 8.5.0 and MariaDB 10.x / MySQL 8.x.
-- Note: Do NOT put semicolons inside comments or COMMENT strings.
-- =============================================================================

START TRANSACTION;

-- -----------------------------------------------------------------------------
-- 1. Add card_modules.card_type (idempotent)
-- -----------------------------------------------------------------------------
SET @card_type_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'card_modules'
    AND COLUMN_NAME = 'card_type'
);

SET @card_type_sql := IF(
  @card_type_exists = 0,
  'ALTER TABLE `card_modules` ADD COLUMN `card_type` VARCHAR(10) NOT NULL DEFAULT ''study'' COMMENT ''Module type: study or tool'' AFTER `acl_value`',
  'SELECT 1'
);

PREPARE card_type_stmt FROM @card_type_sql;

EXECUTE card_type_stmt;

DEALLOCATE PREPARE card_type_stmt;

-- -----------------------------------------------------------------------------
-- 2. Create card_settings
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `card_settings` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  `scope` VARCHAR(10) NOT NULL DEFAULT 'clinic' COMMENT 'Setting scope: clinic or user',
  `user_id` BIGINT(20) NOT NULL DEFAULT 0 COMMENT 'users.id - 0 for clinic settings',
  `setting_key` VARCHAR(64) NOT NULL COMMENT 'Validated setting identifier',
  `setting_value` TEXT COMMENT 'Value stored as text',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp',
  `updated_by` VARCHAR(255) DEFAULT NULL COMMENT 'Username of the last editor',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scope_user_key` (`scope`, `user_id`, `setting_key`),
  KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Clinic and per-user settings for Cardiology';

-- -----------------------------------------------------------------------------
-- 3. Register the Settings tool card
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `card_modules`
  (`code`, `title`, `icon`, `table_name`, `acl_section`, `acl_value`, `card_type`, `sort_order`, `global_active`) VALUES
  ('settings', 'Settings', 'fa-cog', '', 'patients', 'med', 'tool', 900, 1);

COMMIT;
