-- =============================================================================
-- Cardiology Form Module: Upgrade 006 - Synchronize ABPM COMMENT ranges
-- Location: interface/forms/form_cardiology/sql/upgrade_006_schema_sync.sql
--
-- Updates the COMMENT text of form_card_abpm.sbp_24h, dbp_24h and hr_24h to
-- the intended plausibility ranges (sbp 70-250, dbp 40-150, hr 30-150), which
-- match CardInput RANGES in cards/abpm/save.php and table.sql.
--
-- Safe to re-run on existing databases (idempotent conditional DDL).
-- Compatible with OpenEMR 8.2.0 - 8.5.0 and MariaDB 10.x / MySQL 8.x.
-- =============================================================================

START TRANSACTION;

-- -----------------------------------------------------------------------------
-- 1. form_card_abpm.sbp_24h
-- -----------------------------------------------------------------------------
SET @sbp24h_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'form_card_abpm'
    AND COLUMN_NAME = 'sbp_24h'
);

SET @sbp24h_sql := IF(
  @sbp24h_exists = 1,
  'ALTER TABLE `form_card_abpm` MODIFY COLUMN `sbp_24h` SMALLINT DEFAULT NULL COMMENT ''Mean 24-hour systolic blood pressure in mmHg (70 to 250)''',
  'SELECT 1'
);

PREPARE sbp24h_stmt FROM @sbp24h_sql;

EXECUTE sbp24h_stmt;

DEALLOCATE PREPARE sbp24h_stmt;

-- -----------------------------------------------------------------------------
-- 2. form_card_abpm.dbp_24h
-- -----------------------------------------------------------------------------
SET @dbp24h_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'form_card_abpm'
    AND COLUMN_NAME = 'dbp_24h'
);

SET @dbp24h_sql := IF(
  @dbp24h_exists = 1,
  'ALTER TABLE `form_card_abpm` MODIFY COLUMN `dbp_24h` SMALLINT DEFAULT NULL COMMENT ''Mean 24-hour diastolic blood pressure in mmHg (40 to 150)''',
  'SELECT 1'
);

PREPARE dbp24h_stmt FROM @dbp24h_sql;

EXECUTE dbp24h_stmt;

DEALLOCATE PREPARE dbp24h_stmt;

-- -----------------------------------------------------------------------------
-- 3. form_card_abpm.hr_24h
-- -----------------------------------------------------------------------------
SET @hr24h_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'form_card_abpm'
    AND COLUMN_NAME = 'hr_24h'
);

SET @hr24h_sql := IF(
  @hr24h_exists = 1,
  'ALTER TABLE `form_card_abpm` MODIFY COLUMN `hr_24h` SMALLINT DEFAULT NULL COMMENT ''Mean 24-hour heart rate in beats per minute (30 to 150)''',
  'SELECT 1'
);

PREPARE hr24h_stmt FROM @hr24h_sql;

EXECUTE hr24h_stmt;

DEALLOCATE PREPARE hr24h_stmt;

COMMIT;
