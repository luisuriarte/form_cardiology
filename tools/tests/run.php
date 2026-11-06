<?php
/**
 * run.php - Self-tests for tools/check_schema.php
 *
 * Builds small fixture repositories in a temp directory, runs the checker
 * CLI against each, and asserts the expected rule fires (or stays silent).
 * Exits 1 when any test does not behave as expected.
 *
 * Usage: php tools/tests/run.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/check_schema.php';

$PASS = 0;
$FAIL = 0;
$failed = [];

function check(string $name, bool $ok, string $detail = ''): void
{
    global $PASS, $FAIL, $failed;
    if ($ok) {
        $PASS++;
        echo "PASS  $name\n";
    } else {
        $FAIL++;
        $failed[] = $name;
        echo "FAIL  $name" . ($detail !== '' ? " -- $detail" : '') . "\n";
    }
}

function makeRoot(string $label, array $files): string
{
    $dir = sys_get_temp_dir() . '/card_schema_test_' . $label . '_' . bin2hex(random_bytes(4));
    foreach ($files as $rel => $content) {
        $path = $dir . '/' . $rel;
        $parent = dirname($path);
        if (!is_dir($parent)) {
            mkdir($parent, 0777, true);
        }
        file_put_contents($path, $content);
    }
    return $dir;
}

/** @return array{0: int, 1: string} exit code + combined output */
function runChecker(string $root, array $extra = []): array
{
    $checker = dirname(__DIR__) . '/check_schema.php';
    $cmd = 'php ' . escapeshellarg($checker)
        . ' --root=' . escapeshellarg($root)
        . ' --no-live --no-report';
    foreach ($extra as $e) {
        $cmd .= ' ' . escapeshellarg($e);
    }
    $out = [];
    $code = 0;
    exec($cmd . ' 2>&1', $out, $code);
    return [$code, implode("\n", $out)];
}

function expectRule(string $label, string $root, string $rule, bool $shouldFind): void
{
    [$code, $output] = runChecker($root);
    $found = str_contains($output, $rule);
    if ($shouldFind) {
        check($label, $found && $code === 1, "expected $rule and exit 1, got exit $code");
    } else {
        check($label, !$found && $code === 0, "expected no $rule and exit 0, got exit $code");
    }
}

/** Fixtures that assert a WARN (rule present, exit code 0). */
function expectWarn(string $label, string $root, string $rule): void
{
    [$code, $output] = runChecker($root);
    check($label, str_contains($output, $rule) && $code === 0, "expected WARN $rule and exit 0, got exit $code");
}

/** Stub files so R4 does not fire for seeded fixture cards. */
function cardStubs(): array
{
    return [
        'cards/test/new.php' => '<?php // stub',
        'cards/test/view.php' => '<?php // stub',
        'cards/test/print.php' => '<?php // stub',
        'cards/bug/new.php' => '<?php // stub',
        'cards/bug/view.php' => '<?php // stub',
        'cards/bug/print.php' => '<?php // stub',
    ];
}

function withStubs(array $files): array
{
    return array_merge(cardStubs(), $files);
}

// ---------------------------------------------------------------------------
// Parser unit tests
// ---------------------------------------------------------------------------

// COMMENT with commas, parentheses, escaped quotes and "list_id <id>" text.
$tmp = makeRoot('parser_comment', [
    'table.sql' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `form_card_test` (
  `id` BIGINT NOT NULL AUTO_INCREMENT COMMENT 'Primary key, also stored as forms.form_id',
  `valve` VARCHAR(32) DEFAULT NULL COMMENT 'Grade (list_options list_id card_valve_grade, scaled 0-3)',
  `note` TEXT COMMENT 'Free text, with commas (and parentheses) and ''escaped'' quotes',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Test table';
SQL,
]);
$parsed = SchemaChecker::parseSqlFile($tmp . '/table.sql');
$cols = $parsed['tables'][0]['columns'] ?? [];
check('parser: COMMENT with commas and parentheses parsed', ($cols['valve']['comment'] ?? '') === 'Grade (list_options list_id card_valve_grade, scaled 0-3)', json_encode($cols['valve'] ?? null));
check('parser: COMMENT with escaped quotes parsed', ($cols['note']['comment'] ?? '') === "Free text, with commas (and parentheses) and 'escaped' quotes", json_encode($cols['note'] ?? null));
check('parser: display width normalized int(11)==int', SchemaChecker::normalizeType('int', '11', false) === SchemaChecker::normalizeType('int', '', false));
check('parser: tinyint(1) kept distinct', SchemaChecker::normalizeType('tinyint', '1', false) === 'tinyint(1)' && SchemaChecker::normalizeType('tinyint', '4', false) === 'tinyint');

// Conditional ALTER pattern.
$tmp = makeRoot('parser_alter', [
    'table.sql' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `card_modules` (
  `code` VARCHAR(32) NOT NULL COMMENT 'Code (list_options list_id lists)',
  `card_type` VARCHAR(10) NOT NULL DEFAULT 'study' COMMENT 'Module type: study or tool',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Modules';
SQL,
    'sql/upgrade_001_x.sql' => <<<'SQL'
SET @card_type_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'card_modules' AND COLUMN_NAME = 'card_type'
);
SET @card_type_sql := IF(
  @card_type_exists = 0,
  'ALTER TABLE `card_modules` ADD COLUMN `card_type` VARCHAR(10) NOT NULL DEFAULT ''study'' COMMENT ''Module type: study or tool'' AFTER `code`',
  'SELECT 1'
);
PREPARE card_type_stmt FROM @card_type_sql;
EXECUTE card_type_stmt;
DEALLOCATE PREPARE card_type_stmt;
SQL,
]);
$parsed = SchemaChecker::parseSqlFile($tmp . '/sql/upgrade_001_x.sql');
check('parser: conditional ALTER extracted', count($parsed['alters']) === 1 && ($parsed['alters'][0]['column']['name'] ?? '') === 'card_type', json_encode($parsed['alters']));
if (!empty($parsed['alters'])) {
    $col = $parsed['alters'][0]['column'];
    check('parser: conditional ALTER definition complete (default + comment)', $col['default'] === "'study'" && $col['comment'] === 'Module type: study or tool', json_encode($col));
}

// ---------------------------------------------------------------------------
// Rule fixtures (each in its own minimal repo root)
// ---------------------------------------------------------------------------

$baseTable = <<<'SQL'
CREATE TABLE IF NOT EXISTS `form_card_test` (
  `id` BIGINT NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  `pid` BIGINT NOT NULL COMMENT 'Patient id',
  `encounter` BIGINT NOT NULL COMMENT 'Encounter number',
  `cardiology_id` BIGINT NOT NULL COMMENT 'Parent container',
  `user` VARCHAR(255) DEFAULT NULL COMMENT 'Username',
  `activity` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Soft delete flag',
  `status` VARCHAR(10) NOT NULL DEFAULT 'draft' COMMENT 'Workflow status',
  `study_date` DATETIME NOT NULL COMMENT 'Study date and time',
  `val` DECIMAL(4,1) DEFAULT NULL COMMENT 'A measurement, calculated server-side',
  `grade` VARCHAR(32) DEFAULT NULL COMMENT 'Grade (list_options list_id card_valve_grade)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Test card';
INSERT IGNORE INTO `card_modules` (`code`, `title`, `icon`, `table_name`, `sort_order`) VALUES
  ('test', 'Test', 'fa-x', 'form_card_test', 10);
INSERT IGNORE INTO `list_options` (`list_id`, `option_id`, `title`, `seq`, `is_default`, `activity`) VALUES
  ('lists', 'card_valve_grade', 'Cardiology - Valve grade', 0, 0, 1),
  ('card_valve_grade', 'none', 'None', 10, 1, 1),
  ('card_valve_grade', 'mild', 'Mild', 20, 0, 1);
SQL;

$saveHeader = <<<'PHP'
<?php
declare(strict_types=1);
require_once(__DIR__ . '/../../lib/CardInput.php');
const RANGES = [
    'val' => [0.0, 999.0],
];
$p = $_POST;
$pid = 1;
PHP;

// 1. Placeholder count mismatch: one extra ?
expectRule('W2: placeholder mismatch caught', makeRoot('w2', withStubs([
    'table.sql' => $baseTable,
    'cards/test/save.php' => $saveHeader . <<<'PHP'
$val = CardInput::decimal($p['val'] ?? null, 'val');
sqlInsert(
    'INSERT INTO `form_card_test` (`pid`, `encounter`, `cardiology_id`, `user`, `study_date`, `val`)
     VALUES (?, ?, ?, ?, ?, ?, ?)',
    [$pid, 1, 1, 'admin', '2025-01-01 10:00:00', $val]
);
PHP,
])), 'W2', true);

// 2. Unknown column in an INSERT.
expectRule('W1: unknown column caught', makeRoot('w1', withStubs([
    'table.sql' => $baseTable,
    'cards/test/save.php' => $saveHeader . <<<'PHP'
$val = CardInput::decimal($p['val'] ?? null, 'val');
$data = [
    'pid' => $pid,
    'encounter' => 1,
    'cardiology_id' => 1,
    'user' => 'admin',
    'study_date' => '2025-01-01 10:00:00',
    'nope_col' => $val,
];
$columns = array_keys($data);
$sql = 'INSERT INTO `form_card_test` (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
sqlInsert($sql, array_values($data));
PHP,
])), 'W1', true);

// 3. Key repeated in a $data array.
expectRule('W3: duplicate key caught', makeRoot('w3dup', withStubs([
    'table.sql' => $baseTable,
    'cards/test/save.php' => $saveHeader . <<<'PHP'
$val = CardInput::decimal($p['val'] ?? null, 'val');
$data = [
    'pid' => $pid,
    'pid' => $pid,
    'encounter' => 1,
    'cardiology_id' => 1,
    'user' => 'admin',
    'study_date' => '2025-01-01 10:00:00',
    'val' => $val,
];
$columns = array_keys($data);
$sql = 'INSERT INTO `form_card_test` (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
sqlInsert($sql, array_values($data));
PHP,
])), 'W3', true);

// 4. NOT NULL column missing from an INSERT.
expectRule('W4: missing NOT NULL column caught', makeRoot('w4', withStubs([
    'table.sql' => $baseTable,
    'cards/test/save.php' => $saveHeader . <<<'PHP'
$data = [
    'pid' => $pid,
    'encounter' => 1,
    'cardiology_id' => 1,
    'user' => 'admin',
    'val' => null,
];
$columns = array_keys($data);
$sql = 'INSERT INTO `form_card_test` (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
sqlInsert($sql, array_values($data));
PHP,
])), 'W4', true);

// 5. Calculated column read from POST (WARN; exit stays 0).
expectWarn('W5: calculated column from POST caught', makeRoot('w5', withStubs([
    'table.sql' => $baseTable,
    'cards/test/save.php' => $saveHeader . <<<'PHP'
$val = CardInput::decimal($p['val'] ?? null, 'val');
$grade = CardInput::listOption('card_valve_grade', $p['grade'] ?? null, 'grade');
$data = [
    'pid' => $pid,
    'encounter' => 1,
    'cardiology_id' => 1,
    'user' => 'admin',
    'study_date' => '2025-01-01 10:00:00',
    'val' => $val,
    'grade' => $grade,
];
$columns = array_keys($data);
$sql = 'INSERT INTO `form_card_test` (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
sqlInsert($sql, array_values($data));
PHP,
    'cards/test/_form_fields.php' => '<input type="text" name="val"><input type="text" name="grade"><input type="text" name="study_date">',
])), 'W5');

// 6. Range that overflows DECIMAL(4,1).
expectRule('W9: DECIMAL overflow range caught', makeRoot('w9', withStubs([
    'table.sql' => $baseTable,
    'cards/test/save.php' => <<<'PHP'
<?php
declare(strict_types=1);
require_once(__DIR__ . '/../../lib/CardInput.php');
const RANGES = [
    'val' => [0.0, 5000.0],
];
$p = $_POST;
$val = CardInput::decimal($p['val'] ?? null, 'val');
$data = [
    'pid' => 1,
    'encounter' => 1,
    'cardiology_id' => 1,
    'user' => 'admin',
    'study_date' => '2025-01-01 10:00:00',
    'val' => $val,
];
$columns = array_keys($data);
$sql = 'INSERT INTO `form_card_test` (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
sqlInsert($sql, array_values($data));
PHP,
])), 'W9', true);

// 7. List id not matching the COMMENT.
expectRule('W8: list id mismatch caught', makeRoot('w8', withStubs([
    'table.sql' => $baseTable,
    'cards/test/save.php' => $saveHeader . <<<'PHP'
$grade = CardInput::listOption('card_other_list', $p['grade'] ?? null, 'grade');
$data = [
    'pid' => 1,
    'encounter' => 1,
    'cardiology_id' => 1,
    'user' => 'admin',
    'study_date' => '2025-01-01 10:00:00',
    'grade' => $grade,
];
$columns = array_keys($data);
$sql = 'INSERT INTO `form_card_test` (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
sqlInsert($sql, array_values($data));
PHP,
])), 'W8', true);

// 8. Table created in an upgrade but different in table.sql.
expectRule('S1: upgrade-created table differs caught', makeRoot('s1', [
    'table.sql' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `form_card_upgrade` (
  `id` BIGINT NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  `val` INT DEFAULT NULL COMMENT 'A value',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Upgrade table';
SQL,
    'sql/upgrade_001_x.sql' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `form_card_upgrade` (
  `id` BIGINT NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  `val` BIGINT DEFAULT NULL COMMENT 'A value',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Upgrade table';
SQL,
]), 'S1', true);

// 9. ALTER column missing in table.sql.
expectRule('S2: ALTER column missing caught', makeRoot('s2', [
    'table.sql' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `form_card_test` (
  `id` BIGINT NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Test';
SQL,
    'sql/upgrade_001_x.sql' => <<<'SQL'
SET @x_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'form_card_test' AND COLUMN_NAME = 'extra_col'
);
SET @x_sql := IF(
  @x_exists = 0,
  'ALTER TABLE `form_card_test` ADD COLUMN `extra_col` INT DEFAULT NULL COMMENT ''Extra''',
  'SELECT 1'
);
PREPARE x_stmt FROM @x_sql;
EXECUTE x_stmt;
DEALLOCATE PREPARE x_stmt;
SQL,
]), 'S2', true);

// 10. DROP TABLE in table.sql.
expectRule('S5: DROP TABLE caught', makeRoot('s5', [
    'table.sql' => <<<'SQL'
DROP TABLE `form_card_test`;
CREATE TABLE IF NOT EXISTS `form_card_test` (
  `id` BIGINT NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Test';
SQL,
]), 'S5', true);

// 11+12. Positive: COMMENT parsing and conditional ALTER stay silent.
expectRule('parser fixtures stay silent', makeRoot('positive', [
    'table.sql' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `form_card_test` (
  `id` BIGINT NOT NULL AUTO_INCREMENT COMMENT 'Primary key, also stored as forms.form_id',
  `note` TEXT COMMENT 'Free text, with commas (and parentheses) and ''escaped'' quotes',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Test';
SQL,
]), 'S1', false);

// 13. The original bug: 62 columns, 63 value slots, 61 parameters.
$cols62 = [];
$slots63 = [];
$params61 = [];
for ($i = 1; $i <= 62; $i++) {
    $cols62[] = "c$i";
}
for ($i = 1; $i <= 63; $i++) {
    $slots63[] = '?';
}
for ($i = 1; $i <= 61; $i++) {
    $params61[] = "\$v$i";
}
$colDefs = [];
foreach ($cols62 as $cn) {
    $colDefs[] = "`$cn` INT DEFAULT NULL COMMENT 'Column $cn'";
}
$bugTable = "CREATE TABLE IF NOT EXISTS `form_card_bug` (\n"
    . "  `id` BIGINT NOT NULL AUTO_INCREMENT COMMENT 'Primary key',\n"
    . implode(",\n", $colDefs) . "\n"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Bug repro';\n"
    . "INSERT IGNORE INTO `card_modules` (`code`, `title`, `icon`, `table_name`, `sort_order`) VALUES\n"
    . "  ('bug', 'Bug', 'fa-x', 'form_card_bug', 10);\n";
$bugSave = "<?php\ndeclare(strict_types=1);\n"
    . "sqlInsert('INSERT INTO `form_card_bug` (" . implode(', ', $cols62) . ") VALUES (" . implode(', ', $slots63) . ")', [" . implode(', ', $params61) . "]);\n";
expectRule('original bug 62/63/61 caught', makeRoot('bug', withStubs([
    'table.sql' => $bugTable,
    'cards/bug/save.php' => $bugSave,
])), 'W2', true);

// W6S: swapped variables in a $data array.
$swapTable = <<<'SQL'
CREATE TABLE IF NOT EXISTS `form_card_swap` (
  `id` BIGINT NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  `pid` BIGINT NOT NULL COMMENT 'Patient id',
  `encounter` BIGINT NOT NULL COMMENT 'Encounter number',
  `cardiology_id` BIGINT NOT NULL COMMENT 'Parent container',
  `user` VARCHAR(255) DEFAULT NULL COMMENT 'Username',
  `activity` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Soft delete flag',
  `status` VARCHAR(10) NOT NULL DEFAULT 'draft' COMMENT 'Workflow status',
  `study_date` DATETIME NOT NULL COMMENT 'Study date and time',
  `sbp_day` SMALLINT DEFAULT NULL COMMENT 'Daytime systolic',
  `sbp_night` SMALLINT DEFAULT NULL COMMENT 'Nighttime systolic',
  `bsa_m2` DECIMAL(4,2) DEFAULT NULL COMMENT 'Body surface area, calculated server-side',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Swap test card';
INSERT IGNORE INTO `card_modules` (`code`, `title`, `icon`, `table_name`, `sort_order`) VALUES
  ('swap', 'Swap', 'fa-x', 'form_card_swap', 10);
SQL;
expectRule('W6S: swapped variables caught', makeRoot('w6s', withStubs([
    'table.sql' => $swapTable,
    'cards/swap/save.php' => <<<'PHP'
<?php
declare(strict_types=1);
require_once(__DIR__ . '/../../lib/CardInput.php');
$sbpDay = 120;
$sbpNight = 100;
$bsa = 1.8;
$data = [
    'pid' => 1,
    'encounter' => 1,
    'cardiology_id' => 1,
    'user' => 'admin',
    'study_date' => '2025-01-01 10:00:00',
    'sbp_day' => $sbpNight,
    'sbp_night' => $sbpDay,
    'bsa_m2' => $bsa,
];
$columns = array_keys($data);
$sql = 'INSERT INTO `form_card_swap` (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
sqlInsert($sql, array_values($data));
PHP,
    'cards/swap/new.php' => '<?php // stub',
    'cards/swap/view.php' => '<?php // stub',
    'cards/swap/print.php' => '<?php // stub',
])), 'W6S', true);

// COMMENT range vs RANGES mismatch.
expectRule('W9: COMMENT range vs RANGES mismatch caught', makeRoot('w9comment', withStubs([
    'table.sql' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `form_card_test` (
  `id` BIGINT NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  `pid` BIGINT NOT NULL COMMENT 'Patient id',
  `encounter` BIGINT NOT NULL COMMENT 'Encounter number',
  `cardiology_id` BIGINT NOT NULL COMMENT 'Parent container',
  `activity` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Soft delete flag',
  `status` VARCHAR(10) NOT NULL DEFAULT 'draft' COMMENT 'Workflow status',
  `study_date` DATETIME NOT NULL COMMENT 'Study date and time',
  `val` DECIMAL(4,1) DEFAULT NULL COMMENT 'A measurement (0 to 100)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Test card';
INSERT IGNORE INTO `card_modules` (`code`, `title`, `icon`, `table_name`, `sort_order`) VALUES
  ('test', 'Test', 'fa-x', 'form_card_test', 10);
SQL,
    'cards/test/save.php' => <<<'PHP'
<?php
declare(strict_types=1);
require_once(__DIR__ . '/../../lib/CardInput.php');
const RANGES = [
    'val' => [0.0, 999.0],
];
$p = $_POST;
$val = CardInput::decimal($p['val'] ?? null, 'val');
$data = [
    'pid' => 1,
    'encounter' => 1,
    'cardiology_id' => 1,
    'activity' => 1,
    'status' => 'draft',
    'study_date' => '2025-01-01 10:00:00',
    'val' => $val,
];
$columns = array_keys($data);
$sql = 'INSERT INTO `form_card_test` (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
sqlInsert($sql, array_values($data));
PHP,
])), 'W9', true);

// P1: direct echo of a study value in a print.php.
expectRule('P1: direct study echo caught', makeRoot('p1', withStubs([
    'table.sql' => $baseTable,
    'cards/test/save.php' => '<?php // stub',
    'cards/test/print.php' => <<<'PHP'
<?php
$study = ['val' => 42];
?>
<td><?= htmlspecialchars($study['val']) ?></td>
PHP,
])), 'P1', true);

// P2: bare constant MUST be flagged; PHP_EOL/E_ALL MUST NOT.
expectRule('P2: bare constant caught', makeRoot('p2', [
    'cards/test/view.php' => <<<'PHP'
<?php
echo leadII;
PHP,
    'cards/test/print.php' => '<?php // stub',
    'cards/test/new.php' => '<?php // stub',
    'cards/test/save.php' => '<?php // stub',
]), 'P2', true);
expectRule('P2: built-in constants stay silent', makeRoot('p2ok', [
    'cards/test/view.php' => <<<'PHP'
<?php
$a = PHP_EOL . (string)E_ALL;
PHP,
]), 'P2', false);

// P3: variable used but never assigned.
expectWarn('P3: undefined variable caught', makeRoot('p3', [
    'cards/test/view.php' => <<<'PHP'
<?php
echo $neverDefinedVar;
PHP,
]), 'P3');

// C1: column used on a core table missing from a core schema.
expectRule('C1: unknown core column caught', makeRoot('c1', [
    'docs/core_schema/v1.sql' => <<<'SQL'
CREATE TABLE `list_options` (
  `list_id` VARCHAR(32) NOT NULL COMMENT 'List id',
  `option_id` VARCHAR(64) NOT NULL COMMENT 'Option id',
  PRIMARY KEY (`list_id`, `option_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Core list options';
SQL,
    'cards/test/save.php' => <<<'PHP'
<?php
sqlQuery('SELECT list_id, option_id, bogus_col FROM `list_options` WHERE list_id = ?', ['lists']);
PHP,
]), 'C1', true);

// C2: INSERT into a core table omitting a NOT NULL column without default.
expectRule('C2: core NOT NULL omission caught', makeRoot('c2', [
    'docs/core_schema/v1.sql' => <<<'SQL'
CREATE TABLE `users` (
  `id` BIGINT NOT NULL AUTO_INCREMENT COMMENT 'Id',
  `username` VARCHAR(64) NOT NULL COMMENT 'Username',
  `password` VARCHAR(128) NOT NULL COMMENT 'Password hash',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Core users';
SQL,
    'cards/test/save.php' => <<<'PHP'
<?php
sqlInsert('INSERT INTO `users` (`username`) VALUES (?)', ['admin']);
PHP,
]), 'C2', true);

// C3: column present in only one of two core versions. The same case is also
// a C1 ERROR (used but missing in v2), so exit is 1 with C3 present.
expectRule('C3: version-only core column caught', makeRoot('c3', [    'docs/core_schema/v1.sql' => <<<'SQL'
CREATE TABLE `patient_data` (
  `pid` BIGINT NOT NULL COMMENT 'Pid',
  `newer_col` VARCHAR(32) DEFAULT NULL COMMENT 'Newer',
  PRIMARY KEY (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Core patients';
SQL,
    'docs/core_schema/v2.sql' => <<<'SQL'
CREATE TABLE `patient_data` (
  `pid` BIGINT NOT NULL COMMENT 'Pid',
  PRIMARY KEY (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Core patients';
SQL,
    'cards/test/save.php' => <<<'PHP'
<?php
sqlQuery('SELECT pid, newer_col FROM `patient_data` WHERE pid = ?', [1]);
PHP,
]), 'C3', true);

// Print label audit: unit detection (bmi kg/m2, hours, mg, no unit).
function auditUnitFixture(): string
{
    return makeRoot('audit_unit', [
        'table.sql' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `form_card_unitaudit` (
  `id` BIGINT NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  `pid` BIGINT NOT NULL COMMENT 'Patient id',
  `encounter` BIGINT NOT NULL COMMENT 'Encounter number',
  `cardiology_id` BIGINT NOT NULL COMMENT 'Parent container',
  `activity` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Soft delete flag',
  `status` VARCHAR(10) NOT NULL DEFAULT 'draft' COMMENT 'Workflow status',
  `study_date` DATETIME NOT NULL COMMENT 'Date and time the study was performed',
  `bmi` DECIMAL(4,1) DEFAULT NULL COMMENT 'Body mass index in kg/m2, calculated server-side',
  `rec` DECIMAL(5,1) DEFAULT NULL COMMENT 'Recording duration in hours',
  `dose` DECIMAL(5,1) DEFAULT NULL COMMENT 'Current dose in mg',
  `plain` INT DEFAULT NULL COMMENT 'Plain number',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Unit audit';
SQL,
        'cards/unitaudit/print.php' => <<<'PHP'
<?php
$study = [];
$bmi = $study['bmi'] ?? null;
$rec = $study['rec'] ?? null;
$dose = $study['dose'] ?? null;
$plain = $study['plain'] ?? null;
?>
<table class="patient-box">
<tr>
<td class="label"><?= xlt('BMI (kg/m²)') ?></td>
<td class="val"><?= htmlspecialchars(\OpenEMR\Forms\Cardiology\CardPrintReport::formatValue($bmi)) ?></td>
</tr>
<tr>
<td class="label"><?= xlt('Recording (hours)') ?></td>
<td class="val"><?= htmlspecialchars(\OpenEMR\Forms\Cardiology\CardPrintReport::formatValue($rec)) ?></td>
</tr>
<tr>
<td class="label"><?= xlt('Dose (mg)') ?></td>
<td class="val"><?= htmlspecialchars(\OpenEMR\Forms\Cardiology\CardPrintReport::formatValue($dose)) ?></td>
</tr>
<tr>
<td class="label"><?= xlt('Plain') ?></td>
<td class="val"><?= htmlspecialchars(\OpenEMR\Forms\Cardiology\CardPrintReport::formatValue($plain)) ?></td>
</tr>
</table>
PHP,
        'lib/CardPrintReport.php' => '<?php // stub',
    ]);
}

$auditRoot = auditUnitFixture();
$auditChecker = dirname(__DIR__) . '/audit_prints.php';
$auditOut = [];
$auditCode = 0;
exec('php ' . escapeshellarg($auditChecker) . ' --root=' . escapeshellarg($auditRoot) . ' 2>&1', $auditOut, $auditCode);
$auditText = implode("\n", $auditOut);
check('audit: bmi kg/m2 unit ok', !str_contains($auditText, 'UNIT-MISMATCH') || !str_contains($auditText, 'bmi'));
check('audit: bmi kg/m2 not NO-UNIT', $auditCode === 0, "exit $auditCode: $auditText");

$auditRoot2 = makeRoot('audit_unit_mismatch', [
    'table.sql' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `form_card_unitaudit` (
  `id` BIGINT NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  `pid` BIGINT NOT NULL COMMENT 'Patient id',
  `encounter` BIGINT NOT NULL COMMENT 'Encounter number',
  `cardiology_id` BIGINT NOT NULL COMMENT 'Parent container',
  `activity` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Soft delete flag',
  `status` VARCHAR(10) NOT NULL DEFAULT 'draft' COMMENT 'Workflow status',
  `study_date` DATETIME NOT NULL COMMENT 'Date and time the study was performed',
  `rec` DECIMAL(5,1) DEFAULT NULL COMMENT 'Recording duration in hours',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Unit mismatch';
SQL,
    'cards/unitaudit/print.php' => <<<'PHP'
<?php
$study = [];
$rec = $study['rec'] ?? null;
?>
<table class="patient-box">
<tr>
<td class="label"><?= xlt('Recording (minutes)') ?></td>
<td class="val"><?= htmlspecialchars(\OpenEMR\Forms\Cardiology\CardPrintReport::formatValue($rec)) ?></td>
</tr>
</table>
PHP,
]);
$auditOut = [];
$auditCode = 0;
exec('php ' . escapeshellarg($auditChecker) . ' --root=' . escapeshellarg($auditRoot2) . ' 2>&1', $auditOut, $auditCode);
$auditText = implode("\n", $auditOut);
check('audit: hours vs minutes UNIT-MISMATCH flagged', str_contains($auditText, 'UNIT-MISMATCH') && $auditCode === 0, "exit $auditCode");

echo "\n$PASS passed, $FAIL failed\n";
exit($FAIL > 0 ? 1 : 0);
