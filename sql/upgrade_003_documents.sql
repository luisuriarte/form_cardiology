-- =============================================================================
-- Cardiology Form Module: Upgrade 003 - Documents Integration
-- Location: interface/forms/form_cardiology/sql/upgrade_003_documents.sql
--
-- Adds:
--   1. Table card_documents for linking studies to OpenEMR patient documents
--   2. Column card_modules.doc_category_id for category mapping per card
--   3. Category tree seeds under Cardiology (id 22000) and card subcategories
--   4. Updates doc_category_id on study cards
--   5. Synchronizes categories_seq sequence counter
--
-- Safe to re-run on existing databases (idempotent DDL and seeds).
-- Compatible with OpenEMR 8.2.0 - 8.5.0 and MariaDB 10.x / MySQL 8.x.
-- Note: Do NOT put semicolons inside comments or COMMENT strings.
-- =============================================================================

START TRANSACTION;

-- -----------------------------------------------------------------------------
-- 1. Create card_documents table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `card_documents` (
  `id` BIGINT NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  `card_code` VARCHAR(32) NOT NULL COMMENT 'Card code (card_modules.code)',
  `study_id` BIGINT NOT NULL COMMENT 'Study id in the card table',
  `pid` BIGINT NOT NULL COMMENT 'Patient id (patient_data.pid)',
  `document_id` BIGINT NOT NULL COMMENT 'Document id (documents.id)',
  `category_id` BIGINT NOT NULL COMMENT 'Category id at time of linking (categories.id)',
  `created_by` VARCHAR(255) DEFAULT NULL COMMENT 'Username of user who attached the document',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp when attachment was linked',
  `activity` TINYINT NOT NULL DEFAULT 1 COMMENT 'Soft delete flag (1 = active, 0 = unlinked)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_card_study_doc` (`card_code`, `study_id`, `document_id`),
  KEY `idx_pid_card_study` (`pid`, `card_code`, `study_id`),
  KEY `idx_document_id` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Cardiology study document attachments';

-- -----------------------------------------------------------------------------
-- 2. Add doc_category_id column to card_modules (idempotent conditional ALTER)
-- -----------------------------------------------------------------------------
SET @doc_cat_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'card_modules'
    AND COLUMN_NAME = 'doc_category_id'
);

SET @doc_cat_sql := IF(
  @doc_cat_exists = 0,
  'ALTER TABLE `card_modules` ADD COLUMN `doc_category_id` INT NULL COMMENT ''Document category used for attachments of this card'' AFTER `card_type`',
  'SELECT 1'
);

PREPARE doc_cat_stmt FROM @doc_cat_sql;
EXECUTE doc_cat_stmt;
DEALLOCATE PREPARE doc_cat_stmt;

-- -----------------------------------------------------------------------------
-- 3. Seed category tree nodes under Categories (parent 1)
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `categories`
  (`id`, `name`, `value`, `parent`, `lft`, `rght`, `aco_spec`, `codes`)
VALUES
  (22000, 'Cardiology', '', 1, 0, 0, 'patients|docs', ''),
  (22001, 'Echocardiogram', '', 22000, 0, 0, 'patients|docs', ''),
  (22002, 'Stress Test', '', 22000, 0, 0, 'patients|docs', ''),
  (22003, 'ECG', '', 22000, 0, 0, 'patients|docs', ''),
  (22004, 'Holter / ABPM', '', 22000, 0, 0, 'patients|docs', ''),
  (22005, 'Preoperative Evaluation', '', 22000, 0, 0, 'patients|docs', ''),
  (22006, 'Fitness Certificates', '', 22000, 0, 0, 'patients|docs', ''),
  (22007, 'Other Cardiology Documents', '', 22000, 0, 0, 'patients|docs', '');

-- -----------------------------------------------------------------------------
-- 4. Map study cards to their default document category (only where NULL)
-- -----------------------------------------------------------------------------
UPDATE `card_modules` SET `doc_category_id` = 22001 WHERE `code` = 'echo' AND `doc_category_id` IS NULL;
UPDATE `card_modules` SET `doc_category_id` = 22002 WHERE `code` = 'stress_test' AND `doc_category_id` IS NULL;
UPDATE `card_modules` SET `doc_category_id` = 22003 WHERE `code` = 'ecg' AND `doc_category_id` IS NULL;
UPDATE `card_modules` SET `doc_category_id` = 22005 WHERE `code` = 'preop_risk' AND `doc_category_id` IS NULL;
UPDATE `card_modules` SET `doc_category_id` = 22006 WHERE `code` = 'fitness' AND `doc_category_id` IS NULL;

-- -----------------------------------------------------------------------------
-- 5. Advance categories_seq counter if needed (idempotent, never lower it)
-- -----------------------------------------------------------------------------
INSERT INTO `categories_seq` (`id`)
SELECT MAX(`id`) FROM `categories`
WHERE NOT EXISTS (SELECT 1 FROM `categories_seq`);

UPDATE `categories_seq`
SET `id` = (SELECT MAX(`id`) FROM `categories`)
WHERE `id` < (SELECT MAX(`id`) FROM `categories`);

COMMIT;

