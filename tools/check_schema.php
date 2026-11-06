<?php
/**
 * check_schema.php - Deterministic schema/code consistency checker
 *
 * Verifies that the PHP writers/readers of this module agree with the SQL
 * schema (table.sql + sql/upgrade_*.sql + docs/live_schema.sql) without
 * touching a database. See docs/schema_audit.md for the generated report.
 *
 * Usage:
 *   php tools/check_schema.php [--root=<dir>] [--only=<W|R|S prefix>]
 *        [--format=md|text] [--no-live] [--out=<file>]
 *
 * Exit code 1 when at least one ERROR finding exists.
 *
 * Compatible with PHP 8.1+ CLI (standard library only).
 */

declare(strict_types=1);

final class SchemaFinding
{
    public function __construct(
        public string $file,
        public int $line,
        public string $rule,
        public string $severity, // ERROR | WARN | UNRESOLVED | UNPARSED
        public string $message,
    ) {}
}

final class SchemaChecker
{
    /** @var SchemaFinding[] */
    public array $findings = [];

    /** @var array<string, array{tables: array<string, array>, seeds: array<int, array>}> */
    public array $schema = []; // 'table.sql' | 'upgrades' | 'live'

    public string $root;

    /** @var array<string, true> */
    private array $only = [];

    public bool $noLive = false;

    public function __construct(string $root, array $only = [], bool $noLive = false)
    {
        $this->root = rtrim($root, '/\\');
        foreach ($only as $o) {
            $this->only[strtoupper($o)] = true;
        }
        $this->noLive = $noLive;
    }

    private function add(string $file, int $line, string $rule, string $severity, string $message): void
    {
        if (!empty($this->only)) {
            $keep = false;
            foreach ($this->only as $prefix => $_) {
                if (str_starts_with(strtoupper($rule), $prefix)) {
                    $keep = true;
                    break;
                }
            }
            if (!$keep) {
                return;
            }
        }
        $this->findings[] = new SchemaFinding($file, $line, $rule, $severity, $message);
    }

    // ------------------------------------------------------------------
    // Entry point
    // ------------------------------------------------------------------
    public function run(): int
    {
        $this->loadSchema('table.sql', $this->root . '/table.sql');
        $upgradeFiles = $this->upgradeFiles();
        $this->loadSchema('upgrades', null, $upgradeFiles);
        if (!$this->noLive) {
            $live = $this->root . '/docs/live_schema.sql';
            if (is_file($live)) {
                $this->loadSchema('live', $live);
            }
        }

        $this->checkTableVsUpgrades();
        $this->checkLiveVsFiles();
        $this->checkWriters();
        $this->checkReaders();

        return $this->hasErrors() ? 1 : 0;
    }

    public function hasErrors(): bool
    {
        foreach ($this->findings as $f) {
            if ($f->severity === 'ERROR') {
                return true;
            }
        }
        return false;
    }

    /** @return string[] absolute paths of upgrade files in numeric order */
    private function upgradeFiles(): array
    {
        $files = glob($this->root . '/sql/upgrade_*.sql') ?: [];
        sort($files, SORT_NATURAL);
        return $files;
    }

    // ------------------------------------------------------------------
    // SQL parsing
    // ------------------------------------------------------------------
    /**
     * @param string[] $extraFiles
     */
    private function loadSchema(string $name, ?string $file, array $extraFiles = []): void
    {
        $tables = [];
        $seeds = [];
        $files = $file !== null ? [$file] : $extraFiles;
        foreach ($files as $path) {
            if (!is_file($path)) {
                continue;
            }
            $rel = str_replace($this->root . '/', '', $path);
            $parsed = self::parseSqlFile($path);
            foreach ($parsed['tables'] as $t) {
                if (!isset($tables[$t['name']])) {
                    $t['created'] = true;
                    $tables[$t['name']] = $t;
                } else {
                    // Later definitions (upgrade vs table.sql are compared separately).
                    $tables[$t['name']]['created'] = $tables[$t['name']]['created'] ?? false;
                    $tables[$t['name']]['columns'] = array_merge($tables[$t['name']]['columns'], $t['columns']);
                    $tables[$t['name']]['keys'] = array_merge($tables[$t['name']]['keys'], $t['keys']);
                }
            }
            foreach ($parsed['alters'] as $a) {
                $tname = $a['table'];
                if (!isset($tables[$tname])) {
                    $tables[$tname] = ['name' => $tname, 'columns' => [], 'keys' => [], 'engine' => '', 'charset' => '', 'file' => $rel];
                }
                if (!isset($tables[$tname]['columns'][$a['column']['name']])) {
                    $tables[$tname]['columns'][$a['column']['name']] = $a['column'];
                }
            }
            foreach ($parsed['seeds'] as $s) {
                $s['file'] = $rel;
                $seeds[] = $s;
            }
            foreach ($parsed['unparsed'] as $u) {
                $this->add($rel, $u['line'], 'PARSE', 'UNPARSED', $u['text']);
            }
            foreach ($parsed['raw'] as $r) {
                $this->checkRawStatement($rel, $r);
            }
        }
        $this->schema[$name] = ['tables' => $tables, 'seeds' => $seeds];
    }

    /**
     * Parse one SQL file into tables / alters / seeds / unparsed / raw statements.
     *
     * @return array{tables: array, alters: array, seeds: array, unparsed: array, raw: array}
     */
    public static function parseSqlFile(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return ['tables' => [], 'alters' => [], 'seeds' => [], 'unparsed' => [], 'raw' => []];
        }
        $stripped = self::stripComments($content);
        $statements = self::splitStatements($stripped);
        $result = ['tables' => [], 'alters' => [], 'seeds' => [], 'unparsed' => [], 'raw' => []];

        foreach ($statements as $st) {
            $sql = trim($st['sql']);
            $line = $st['line'];
            if ($sql === '') {
                continue;
            }
            $result['raw'][] = ['sql' => $sql, 'line' => $line, 'file' => $path];

            // Conditional ALTER pattern: extract the prepared string.
            // Scan quoted strings properly ('' escapes) instead of a naive regex.
            $quoted = self::quotedStrings($sql);
            $handled = false;
            foreach ($quoted as $inner) {
                if (stripos($inner, 'ALTER TABLE') === false) {
                    continue;
                }
                $handled = true;
                $alter = self::parseAlterAddColumn($inner);
                if ($alter !== null) {
                    $alter['line'] = $line;
                    $result['alters'][] = $alter;
                } else {
                    $result['unparsed'][] = ['line' => $line, 'text' => 'Conditional ALTER not parsed: ' . substr($inner, 0, 120)];
                }
            }
            if ($handled) {
                continue;
            }

            if (preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`([^`]+)`\s*\(/is', $sql, $m)) {
                $table = self::parseCreateTable($sql, $m[1], $line, $path);
                if ($table !== null) {
                    $table['file'] = str_replace('\\', '/', $path);
                    $result['tables'][] = $table;
                } else {
                    $result['unparsed'][] = ['line' => $line, 'text' => 'CREATE TABLE not parsed: ' . substr(preg_replace('/\s+/', ' ', $sql), 0, 120)];
                }
                continue;
            }

            if (preg_match('/^ALTER\s+TABLE\s+`([^`]+)`/is', $sql, $m)) {
                $alter = self::parseAlterAddColumn($sql);
                if ($alter !== null) {
                    $alter['line'] = $line;
                    $result['alters'][] = $alter;
                } else {
                    $result['unparsed'][] = ['line' => $line, 'text' => 'ALTER TABLE not parsed: ' . substr(preg_replace('/\s+/', ' ', $sql), 0, 120)];
                }
                continue;
            }

            // INSERT ... SELECT (guarded, no literal rows to model).
            if (preg_match('/^INSERT\s+(IGNORE\s+)?INTO\s+`[^`]+`[^V]*\bSELECT\b/is', $sql)) {
                continue;
            }

            if (preg_match('/^INSERT\s+(IGNORE\s+)?INTO\s+`([^`]+)`/is', $sql, $m)) {
                $seed = self::parseInsertRows($sql, $m[2]);
                if ($seed !== null) {
                    $seed['line'] = $line;
                    $seed['ignore'] = !empty($m[1]);
                    $result['seeds'][] = $seed;
                } else {
                    $result['unparsed'][] = ['line' => $line, 'text' => 'INSERT not parsed: ' . substr(preg_replace('/\s+/', ' ', $sql), 0, 120)];
                }
                continue;
            }

            // Known statements that need no schema modelling.
            if (preg_match('/^(SET|PREPARE|EXECUTE|DEALLOCATE|START|BEGIN|COMMIT|ROLLBACK|SELECT|USE|LOCK|UNLOCK|UPDATE|DROP\s+TABLE\s+IF\s+EXISTS|DROP\s+TEMPORARY|CREATE\s+(TEMPORARY\s+)?TABLE\s+IF\s+NOT\s+EXISTS\s+`tmp_)/is', $sql)) {
                continue;
            }

            $result['unparsed'][] = ['line' => $line, 'text' => 'Unparsed statement: ' . substr(preg_replace('/\s+/', ' ', $sql), 0, 120)];
        }

        return $result;
    }

    /** Extract single-quoted string literals, unescaping '' to '. */
    public static function quotedStrings(string $sql): array
    {
        $out = [];
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            if ($sql[$i] !== "'") {
                continue;
            }
            $buf = '';
            $i++;
            while ($i < $len) {
                if ($sql[$i] === "'") {
                    if ($i + 1 < $len && $sql[$i + 1] === "'") {
                        $buf .= "'";
                        $i += 2;
                        continue;
                    }
                    break;
                }
                $buf .= $sql[$i];
                $i++;
            }
            $out[] = $buf;
        }
        return $out;
    }

    /** Strip -- and /* *\/ comments, preserving line numbers and strings. */
    public static function stripComments(string $sql): string
    {
        $out = '';
        $len = strlen($sql);
        $i = 0;
        $inStr = false;
        while ($i < $len) {
            $ch = $sql[$i];
            if ($inStr) {
                $out .= $ch;
                if ($ch === "'") {
                    if ($i + 1 < $len && $sql[$i + 1] === "'") {
                        $out .= "'";
                        $i += 2;
                        continue;
                    }
                    $inStr = false;
                }
                $i++;
                continue;
            }
            if ($ch === "'") {
                $inStr = true;
                $out .= $ch;
                $i++;
                continue;
            }
            if ($ch === '/' && $i + 1 < $len && $sql[$i + 1] === '*') {
                // mysqldump conditional comments: keep inner SQL for /*!...*/ versioned code? Skip whole block.
                $end = strpos($sql, '*/', $i + 2);
                if ($end === false) {
                    break;
                }
                $block = substr($sql, $i, $end + 2 - $i);
                $out .= str_repeat("\n", substr_count($block, "\n"));
                $i = $end + 2;
                continue;
            }
            if ($ch === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
                $end = strpos($sql, "\n", $i);
                if ($end === false) {
                    break;
                }
                $out .= "\n";
                $i = $end + 1;
                continue;
            }
            $out .= $ch;
            $i++;
        }
        return $out;
    }

    /**
     * Split into statements on ';' outside single-quoted strings.
     *
     * @return array<int, array{sql: string, line: int}>
     */
    public static function splitStatements(string $sql): array
    {
        $stmts = [];
        $buf = '';
        $line = 1;
        $startLine = 1;
        $inStr = false;
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            if ($ch === "\n") {
                $line++;
            }
            if ($inStr) {
                $buf .= $ch;
                if ($ch === "'") {
                    if ($i + 1 < $len && $sql[$i + 1] === "'") {
                        $buf .= "'";
                        $i++;
                        continue;
                    }
                    $inStr = false;
                }
                continue;
            }
            if ($ch === "'") {
                $inStr = true;
                if (trim($buf) === '') {
                    $startLine = $line;
                }
                $buf .= $ch;
                continue;
            }
            if ($ch === ';') {
                $stmts[] = ['sql' => $buf, 'line' => $startLine];
                $buf = '';
                $startLine = $line;
                continue;
            }
            if (trim($buf) === '' && ($ch === ' ' || $ch === "\t" || $ch === "\r" || $ch === "\n")) {
                continue;
            }
            if (trim($buf) === '') {
                $startLine = $line;
            }
            $buf .= $ch;
        }
        if (trim($buf) !== '') {
            $stmts[] = ['sql' => $buf, 'line' => $startLine];
        }
        return $stmts;
    }

    /** @return array|null table array */
    private static function parseCreateTable(string $sql, string $name, int $line, string $path): ?array
    {
        $open = strpos($sql, '(');
        if ($open === false) {
            return null;
        }
        $close = self::matchingParen($sql, $open);
        if ($close === false) {
            return null;
        }
        $body = substr($sql, $open + 1, $close - $open - 1);
        $tail = substr($sql, $close + 1);
        $columns = [];
        $keys = [];
        foreach (self::splitTopLevel($body) as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            if ($item[0] === '`') {
                $col = self::parseColumnDef($item);
                if ($col !== null) {
                    $col['line'] = $line;
                    $columns[$col['name']] = $col;
                }
            } elseif (preg_match('/^(PRIMARY\s+KEY|UNIQUE\s+KEY|KEY|UNIQUE|INDEX|FULLTEXT|SPATIAL)\b/i', $item)) {
                $keys[] = trim(preg_replace('/\s+/', ' ', $item));
            } else {
                // CONSTRAINT / FOREIGN KEY / CHECK: record as key line (compared textually).
                $keys[] = trim(preg_replace('/\s+/', ' ', $item));
            }
        }
        $engine = '';
        $charset = '';
        if (preg_match('/ENGINE\s*=\s*(\w+)/i', $tail, $m)) {
            $engine = $m[1];
        }
        if (preg_match('/CHARSET\s*=\s*(\w+)/i', $tail, $m)) {
            $charset = $m[1];
        }
        return [
            'name' => $name, 'columns' => $columns, 'keys' => $keys,
            'engine' => $engine, 'charset' => $charset,
            'file' => $path, 'line' => $line,
            'if_not_exists' => (bool)preg_match('/^CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS/i', $sql),
        ];
    }

    /** @return array|null column array */
    public static function parseColumnDef(string $def): ?array
    {
        if (!preg_match('/^`([^`]+)`\s+(.+)$/s', trim($def), $m)) {
            return null;
        }
        $name = $m[1];
        $rest = trim($m[2]);
        // Type: word + optional (args) + optional UNSIGNED.
        if (!preg_match('/^([A-Z_]+)(?:\(([^)]*)\))?(\s+UNSIGNED)?/i', $rest, $tm)) {
            return null;
        }
        $base = strtolower($tm[1]);
        $args = isset($tm[2]) ? trim($tm[2]) : '';
        $unsigned = !empty($tm[3]);
        $type = self::normalizeType($base, $args, $unsigned);

        $notNull = (bool)preg_match('/\bNOT\s+NULL\b/i', $rest);
        $autoInc = (bool)preg_match('/\bAUTO_INCREMENT\b/i', $rest);
        $default = self::extractDefault($rest);
        $comment = self::extractComment($rest);
        $onUpdate = (bool)preg_match('/\bON\s+UPDATE\b/i', $rest);

        return [
            'name' => $name,
            'type' => $type,
            'base' => $base,
            'args' => $args,
            'unsigned' => $unsigned,
            'not_null' => $notNull,
            'auto_increment' => $autoInc,
            'default' => $default,
            'comment' => $comment,
            'on_update' => $onUpdate,
            'nullable' => !$notNull,
        ];
    }

    public static function normalizeType(string $base, string $args, bool $unsigned): string
    {
        $base = strtolower($base);
        $args = trim(preg_replace('/\s+/', '', $args));
        $widthless = ['int' => true, 'bigint' => true, 'smallint' => true, 'mediumint' => true, 'serial' => true];
        if (isset($widthless[$base])) {
            $args = '';
        }
        if ($base === 'tinyint' && $args !== '1') {
            $args = '';
        }
        $t = $base . ($args !== '' ? "($args)" : '');
        if ($unsigned) {
            $t .= ' unsigned';
        }
        return $t;
    }

    /** Extract DEFAULT value normalized: 'NULL', number, "'string'", 'CURRENT_TIMESTAMP', or null when absent. */
    private static function extractDefault(string $rest): ?string
    {
        if (!preg_match('/\bDEFAULT\b\s+(\'[^\']*(?:\'\'[^\']*)*\'|CURRENT_TIMESTAMP(?:\(\))?|NULL|-?\d+(?:\.\d+)?|[A-Z_]+(?:\([^)]*\))?)/i', $rest, $m)) {
            return null;
        }
        $v = trim($m[1]);
        if (strcasecmp($v, 'NULL') === 0) {
            return 'NULL';
        }
        if (preg_match('/^CURRENT_TIMESTAMP/i', $v)) {
            return 'CURRENT_TIMESTAMP';
        }
        if ($v !== '' && $v[0] === "'") {
            return strtolower($v); // keep quotes; compared case-insensitively for keywords only
        }
        return $v;
    }

    private static function extractComment(string $rest): string
    {
        if (!preg_match("/\\bCOMMENT\\s+'((?:[^']|'')*)'/i", $rest, $m)) {
            return '';
        }
        return str_replace("''", "'", $m[1]);
    }

    /** @return array|null ['table'=>..., 'column'=>...] */
    private static function parseAlterAddColumn(string $sql): ?array
    {
        if (!preg_match('/ALTER\s+TABLE\s+`([^`]+)`\s+ADD\s+COLUMN\s+`([^`]+)`\s+(.+)$/is', trim($sql), $m)) {
            return null;
        }
        $col = self::parseColumnDef('`' . $m[2] . '` ' . trim($m[3]));
        if ($col === null) {
            return null;
        }
        return ['table' => $m[1], 'column' => $col];
    }

    /**
     * Parse INSERT rows. Returns list of ['table'=>, 'columns'=>[], 'rows'=>[canonical tuple,...]] or null.
     */
    public static function parseInsertRows(string $sql, string $table): ?array
    {
        if (!preg_match('/INSERT\s+(?:IGNORE\s+)?INTO\s+`[^`]+`\s*\(([^)]*)\)\s*VALUES\s*(.+)$/is', $sql, $m)) {
            return null;
        }
        $cols = array_map(static fn($c) => trim(trim($c, " \t`"), " \t\r\n"), explode(',', $m[1]));
        $valuesPart = trim($m[2]);
        $rowsRaw = self::splitValueTuples($valuesPart);
        if ($rowsRaw === null) {
            return null;
        }
        $rows = [];
        foreach ($rowsRaw as $tuple) {
            $vals = self::splitTopLevel($tuple);
            $resolved = [];
            foreach ($vals as $v) {
                $resolved[] = self::resolveLiteral(trim($v));
            }
            $rows[] = implode('|', $resolved);
        }
        return ['table' => $table, 'columns' => $cols, 'rows' => $rows];
    }

    /** Resolve a literal: string, number, NULL, CHAR(n), CONCAT of literals. */
    public static function resolveLiteral(string $v): string
    {
        $v = trim($v);
        if (preg_match("/^'((?:[^']|'')*)'$/s", $v, $m)) {
            return str_replace("''", "'", $m[1]);
        }
        if (preg_match('/^CHAR\(\s*(\d+)\s*\)$/i', $v, $m)) {
            return chr((int)$m[1]);
        }
        if (preg_match('/^CONCAT\((.*)\)$/is', $v, $m)) {
            $parts = self::splitTopLevel($m[1]);
            $out = '';
            foreach ($parts as $p) {
                $out .= self::resolveLiteral($p);
            }
            return $out;
        }
        if (preg_match('/^(NOW|CURRENT_TIMESTAMP)\(\)$/i', $v)) {
            return $v;
        }
        return $v;
    }

    /** Split comma-separated items at paren depth 0, respecting quotes. */
    public static function splitTopLevel(string $s): array
    {
        $parts = [];
        $buf = '';
        $depth = 0;
        $inStr = false;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $ch = $s[$i];
            if ($inStr) {
                $buf .= $ch;
                if ($ch === "'") {
                    if ($i + 1 < $len && $s[$i + 1] === "'") {
                        $buf .= "'";
                        $i++;
                        continue;
                    }
                    $inStr = false;
                }
                continue;
            }
            if ($ch === "'") {
                $inStr = true;
                $buf .= $ch;
                continue;
            }
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
            } elseif ($ch === ',' && $depth === 0) {
                $parts[] = $buf;
                $buf = '';
                continue;
            }
            $buf .= $ch;
        }
        if (trim($buf) !== '') {
            $parts[] = $buf;
        }
        return $parts;
    }

    /** Split "(...),(...)" value tuples. Returns inner strings or null. */
    private static function splitValueTuples(string $s): ?array
    {
        $tuples = [];
        $len = strlen($s);
        $i = 0;
        while ($i < $len) {
            while ($i < $len && ($s[$i] === ' ' || $s[$i] === "\t" || $s[$i] === "\n" || $s[$i] === "\r" || $s[$i] === ',')) {
                $i++;
            }
            if ($i >= $len) {
                break;
            }
            if ($s[$i] !== '(') {
                return null;
            }
            $close = self::matchingParen($s, $i);
            if ($close === false) {
                return null;
            }
            $tuples[] = substr($s, $i + 1, $close - $i - 1);
            $i = $close + 1;
        }
        return $tuples;
    }

    private static function matchingParen(string $s, int $open): int|false
    {
        $depth = 0;
        $inStr = false;
        $len = strlen($s);
        for ($i = $open; $i < $len; $i++) {
            $ch = $s[$i];
            if ($inStr) {
                if ($ch === "'") {
                    if ($i + 1 < $len && $s[$i + 1] === "'") {
                        $i++;
                        continue;
                    }
                    $inStr = false;
                }
                continue;
            }
            if ($ch === "'") {
                $inStr = true;
            } elseif ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }
        return false;
    }

    // ------------------------------------------------------------------
    // S rules: table.sql vs upgrades vs live
    // ------------------------------------------------------------------
    private function checkRawStatement(string $rel, array $r): void
    {
        $sql = $r['sql'];
        $line = $r['line'];
        // S5/S6 conventions apply to install/upgrade files only, not to the
        // mysqldump-style live schema (which legitimately uses DROP IF EXISTS
        // and bare CREATE TABLE).
        if ($rel === 'docs/live_schema.sql') {
            return;
        }
        if (preg_match('/^\s*DROP\s+TABLE\b/i', $sql)) {
            $this->add($rel, $line, 'S5', 'ERROR', 'DROP TABLE is not allowed');
        }
        if (preg_match('/^\s*CREATE\s+TABLE\s+`([^`]+)`/i', $sql, $m)) {
            $this->add($rel, $line, 'S6', 'ERROR', "Bare CREATE TABLE for `{$m[1]}` (missing IF NOT EXISTS)");
        }
        if (preg_match('/^\s*ALTER\s+TABLE\s+`[^`]+`\s+ADD\s+COLUMN\b/i', $sql)) {
            $this->add($rel, $line, 'S6', 'ERROR', 'Bare ALTER TABLE ... ADD COLUMN (use the conditional information_schema pattern)');
        }
        if (preg_match('/^\s*INSERT\s+INTO\b/i', $sql)) {
            // Guarded inserts (NOT EXISTS / ON DUPLICATE KEY) are idempotent.
            if (!preg_match('/\bNOT\s+EXISTS\b/i', $sql) && !preg_match('/\bON\s+DUPLICATE\s+KEY\b/i', $sql)) {
                $this->add($rel, $line, 'S6', 'ERROR', 'Bare INSERT without IGNORE or guard');
            }
        }
        // Semicolon inside COMMENT string or quoted value: splitStatements would have
        // split there, so detect by checking for unterminated quote patterns is unreliable;
        // instead scan the raw file text in checkConventions().
    }

    private function checkTableVsUpgrades(): void
    {
        $base = $this->schema['table.sql']['tables'] ?? [];
        $upgFile = $this->schema['upgrades']['tables'] ?? [];

        // S5 conventions on table.sql
        $this->checkConventions();

        // S1 / S3: tables CREATED by upgrades must match table.sql.
        // (Tables only ALTERed by upgrades, e.g. card_modules, are covered by S2.)
        foreach ($upgFile as $name => $upgTable) {
            if (empty($upgTable['created'])) {
                continue;
            }
            if (!isset($base[$name])) {
                $this->add($upgTable['file'] ?? 'sql/upgrade_*.sql', $upgTable['line'] ?? 1, 'S1', 'ERROR', "Table `$name` is created by an upgrade script but not found in table.sql");
                continue;
            }
            $bt = $base[$name];
            foreach ($upgTable['columns'] as $cname => $uc) {
                if (!isset($bt['columns'][$cname])) {
                    $this->add($upgTable['file'] ?? 'sql/upgrade_*.sql', $upgTable['line'] ?? 1, 'S3', 'ERROR', "Table `$name`: column `$cname` is in table.sql? No - created by upgrade but missing in table.sql");
                    continue;
                }
                $bc = $bt['columns'][$cname];
                $diff = self::columnDiff($bc, $uc);
                if ($diff !== '') {
                    $this->add('table.sql', $bt['line'] ?? 1, 'S1', 'ERROR', "Table `$name` column `$cname` differs between table.sql and upgrade: $diff (table.sql: " . self::colSummary($bc) . " | upgrade: " . self::colSummary($uc) . ')');
                }
            }
            foreach ($bt['columns'] as $cname => $bc) {
                if (!isset($upgTable['columns'][$cname])) {
                    $this->add('table.sql', $bt['line'] ?? 1, 'S3', 'ERROR', "Table `$name`: column `$cname` exists in table.sql but is never created by the upgrade script (existing installs will miss it; add a conditional ALTER to sql/upgrade_006_schema_sync.sql)");
                }
            }
            // Keys comparison (textual, normalized whitespace/case).
            $bk = array_map(static fn($k) => strtolower(preg_replace('/\s+/', ' ', trim($k))), $bt['keys']);
            $uk = array_map(static fn($k) => strtolower(preg_replace('/\s+/', ' ', trim($k))), $upgTable['keys']);
            sort($bk);
            sort($uk);
            if ($bk !== $uk) {
                $this->add('table.sql', $bt['line'] ?? 1, 'S1', 'ERROR', "Table `$name` keys differ (table.sql: [" . implode('; ', $bk) . "] | upgrade: [" . implode('; ', $uk) . '])');
            }
        }

        // S2: ALTER ADD COLUMN targets must match table.sql.
        $upgAlters = [];
        // Alters were merged into upgrades tables; re-derive from files for line accuracy.
        foreach ($this->upgradeFiles() as $uf) {
            $parsed = self::parseSqlFile($uf);
            foreach ($parsed['alters'] as $a) {
                $upgAlters[] = ['file' => str_replace($this->root . '/', '', $uf), 'alter' => $a];
            }
        }
        foreach ($upgAlters as $ua) {
            $a = $ua['alter'];
            $tname = $a['table'];
            $cname = $a['column']['name'];
            if (!isset($base[$tname]['columns'][$cname])) {
                $this->add($ua['file'], $a['line'] ?? 1, 'S2', 'ERROR', "ALTER adds `$tname`.`$cname` but table.sql does not have that column");
                continue;
            }
            $diff = self::columnDiff($base[$tname]['columns'][$cname], $a['column']);
            if ($diff !== '') {
                $this->add($ua['file'], $a['line'] ?? 1, 'S2', 'ERROR', "ALTER column `$tname`.`$cname` definition differs from table.sql: $diff");
            }
        }

        // S4: seeds.
        $this->checkSeeds($base);
    }

    private static function colSummary(array $c): string
    {
        return $c['type'] . ($c['not_null'] ? ' NOT NULL' : ' NULL')
            . ' DEFAULT ' . var_export($c['default'], true)
            . " COMMENT '" . $c['comment'] . "'";
    }

    /** Returns '' when equal, else a short difference description. */
    public static function columnDiff(array $a, array $b): string
    {
        $diffs = [];
        if ($a['type'] !== $b['type']) {
            $diffs[] = "type {$a['type']} vs {$b['type']}";
        }
        if ($a['not_null'] !== $b['not_null']) {
            $diffs[] = 'nullability ' . ($a['not_null'] ? 'NOT NULL' : 'NULL') . ' vs ' . ($b['not_null'] ? 'NOT NULL' : 'NULL');
        }
        $da = self::normDefault($a);
        $db = self::normDefault($b);
        if ($da !== $db) {
            $diffs[] = 'default ' . var_export($da, true) . ' vs ' . var_export($db, true);
        }
        if ($a['comment'] !== $b['comment']) {
            $diffs[] = "comment '{$a['comment']}' vs '{$b['comment']}'";
        }
        if ($a['auto_increment'] !== $b['auto_increment']) {
            $diffs[] = 'auto_increment differs';
        }
        return implode('; ', $diffs);
    }

    private static function normDefault(array $c): string
    {
        $d = $c['default'];
        if ($d === null || $d === 'NULL') {
            return $c['nullable'] ? 'NULL' : '<none>';
        }
        if (strcasecmp($d, 'CURRENT_TIMESTAMP') === 0) {
            return 'CURRENT_TIMESTAMP';
        }
        return $d;
    }

    private function checkConventions(): void
    {
        $path = $this->root . '/table.sql';
        if (!is_file($path)) {
            return;
        }
        $content = file_get_contents($path);
        // Semicolon inside COMMENT strings: look for COMMENT '...;...' patterns.
        if (preg_match_all("/COMMENT\\s+'((?:[^']|'')*)'/i", $content, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[1] as [$text, $off]) {
                if (str_contains($text, ';')) {
                    $line = substr_count(substr($content, 0, $off), "\n") + 1;
                    $this->add('table.sql', $line, 'S5', 'ERROR', "Semicolon inside COMMENT string: '{$text}'");
                }
            }
        }
        foreach (($this->schema['table.sql']['tables'] ?? []) as $name => $t) {
            if (empty($t['engine']) || strcasecmp($t['engine'], 'InnoDB') !== 0) {
                $this->add('table.sql', $t['line'] ?? 1, 'S5', 'WARN', "Table `$name` engine is not InnoDB");
            }
            if (empty($t['charset']) || strcasecmp($t['charset'], 'utf8mb4') !== 0) {
                $this->add('table.sql', $t['line'] ?? 1, 'S5', 'WARN', "Table `$name` charset is not utf8mb4");
            }
            if (empty($t['comment'] ?? '')) {
                // Table-level COMMENT is in tail, not captured per table; check tail text.
            }
            foreach ($t['columns'] as $cname => $c) {
                if (($c['comment'] ?? '') === '') {
                    $this->add('table.sql', $c['line'] ?? $t['line'] ?? 1, 'S5', 'WARN', "Column `$name`.`$cname` has no COMMENT");
                }
            }
        }
    }

    private function checkSeeds(array $base): void
    {
        $baseSeeds = $this->schema['table.sql']['seeds'] ?? [];
        $upgSeeds = $this->schema['upgrades']['seeds'] ?? [];

        $canon = static function (array $s): string {
            $cols = $s['columns'] ?? [];
            $rows = $s['rows'] ?? [];
            $out = [];
            foreach ($rows as $r) {
                $out[] = $s['table'] . '(' . implode(',', $cols) . ')=' . $r;
            }
            return implode("\n", $out);
        };
        $baseSet = [];
        foreach ($baseSeeds as $s) {
            foreach (($s['rows'] ?? []) as $r) {
                $baseSet[$s['table'] . '(' . implode(',', $s['columns'] ?? []) . ')=' . $r] = $s;
            }
        }
        foreach ($upgSeeds as $s) {
            foreach (($s['rows'] ?? []) as $r) {
                $key = $s['table'] . '(' . implode(',', $s['columns'] ?? []) . ')=' . $r;
                if (!isset($baseSet[$key])) {
                    $this->add($s['file'], $s['line'] ?? 1, 'S4', 'ERROR', "Seed row in upgrade missing from table.sql: $key");
                }
            }
        }
        // Reverse: rows in table.sql for tables/lists introduced by upgrades should appear in upgrades.
        $upgTables = array_filter(array_keys($this->schema['upgrades']['tables'] ?? []), fn($n) => !empty($this->schema['upgrades']['tables'][$n]['created']));
        $upgLists = [];
        $upgCodes = [];
        foreach ($upgSeeds as $s) {
            if (($s['table'] ?? '') === 'list_options') {
                $colsS = $s['columns'] ?? [];
                $li = array_search('list_id', $colsS, true);
                $oi = array_search('option_id', $colsS, true);
                foreach (($s['rows'] ?? []) as $r) {
                    $p = explode('|', $r);
                    if (($p[$li] ?? '') === 'lists' && $oi !== false) {
                        $upgLists[$p[$oi]] = true;
                    }
                }
            }
            if (($s['table'] ?? '') === 'card_modules') {
                $colsS = $s['columns'] ?? [];
                $ciS = array_search('code', $colsS, true);
                foreach (($s['rows'] ?? []) as $r) {
                    $p = explode('|', $r);
                    if ($ciS !== false) {
                        $upgCodes[$p[$ciS]] = true;
                    }
                }
            }
        }
        $upgSet = [];
        foreach ($upgSeeds as $s) {
            foreach (($s['rows'] ?? []) as $r) {
                $upgSet[$s['table'] . '(' . implode(',', $s['columns'] ?? []) . ')=' . $r] = true;
            }
        }
        foreach ($baseSeeds as $s) {
            $introduced = in_array($s['table'], $upgTables, true);
            if (!$introduced && $s['table'] === 'list_options') {
                $idx = array_search('list_id', $s['columns'] ?? [], true);
                if ($idx !== false) {
                    foreach (($s['rows'] ?? []) as $r) {
                        $parts = explode('|', $r);
                        if (isset($upgLists[$parts[$idx] ?? ''])) {
                            $introduced = true;
                            break;
                        }
                    }
                }
            }
            if (!$introduced && $s['table'] === 'card_modules') {
                $idx = array_search('code', $s['columns'] ?? [], true);
                if ($idx !== false) {
                    foreach (($s['rows'] ?? []) as $r) {
                        $parts = explode('|', $r);
                        if (isset($upgCodes[$parts[$idx] ?? ''])) {
                            $introduced = true;
                            break;
                        }
                    }
                }
            }
            if (!$introduced) {
                continue;
            }
            foreach (($s['rows'] ?? []) as $r) {
                $key = $s['table'] . '(' . implode(',', $s['columns'] ?? []) . ')=' . $r;
                if (!isset($upgSet[$key])) {
                    $this->add('table.sql', $s['line'] ?? 1, 'S4', 'WARN', "Seed row in table.sql missing from upgrade scripts: $key");
                }
            }
        }
    }

    private function checkLiveVsFiles(): void
    {
        if ($this->noLive || !isset($this->schema['live'])) {
            return;
        }
        $fileTables = $this->schema['table.sql']['tables'] ?? [];
        foreach (($this->schema['upgrades']['tables'] ?? []) as $name => $t) {
            if (!isset($fileTables[$name])) {
                $fileTables[$name] = $t;
            } else {
                $fileTables[$name]['columns'] = array_merge($fileTables[$name]['columns'], $t['columns']);
            }
        }
        foreach ($fileTables as $name => $ft) {
            if (!isset($this->schema['live']['tables'][$name])) {
                $this->add('table.sql', $ft['line'] ?? 1, 'S7', 'ERROR', "Table `$name` exists in files but not in docs/live_schema.sql");
            }
        }
        foreach (($this->schema['live']['tables'] ?? []) as $name => $lt) {
            if (!isset($fileTables[$name])) {
                $this->add('docs/live_schema.sql', $lt['line'] ?? 1, 'S7', 'WARN', "Table `$name` exists in live schema but not in files");
                continue;
            }
            foreach ($ft = $fileTables[$name]['columns'] as $cname => $fc) {
                if (!isset($lt['columns'][$cname])) {
                    $this->add('table.sql', $fc['line'] ?? 1, 'S7', 'ERROR', "Column `$name`.`$cname` exists in files but not in live schema");
                    continue;
                }
                $diff = self::columnDiff($fc, $lt['columns'][$cname]);
                if ($diff !== '') {
                    $this->add('table.sql', $fc['line'] ?? 1, 'S7', 'ERROR', "Column `$name`.`$cname` differs from live schema: $diff");
                }
            }
            foreach ($lt['columns'] as $cname => $lc) {
                if (!isset($ft[$cname])) {
                    $this->add('docs/live_schema.sql', $lc['line'] ?? 1, 'S7', 'WARN', "Column `$name`.`$cname` exists in live schema but not in files");
                }
            }
        }
    }

    // ------------------------------------------------------------------
    // PHP scanning helpers
    // ------------------------------------------------------------------
    /** @return string[] */
    private function phpFiles(): array
    {
        $dirs = ['cards', 'lib', 'templates'];
        $files = [];
        foreach ($dirs as $d) {
            $base = $this->root . '/' . $d;
            if (!is_dir($base)) {
                continue;
            }
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile() && $f->getExtension() === 'php') {
                    $files[] = $f->getPathname();
                }
            }
        }
        foreach (['documents_api.php', 'documents_upload.php', 'documents_view.php', 'report.php', 'view.php', 'new.php', 'save.php', 'config.php'] as $f) {
            $p = $this->root . '/' . $f;
            if (is_file($p)) {
                $files[] = $p;
            }
        }
        sort($files);
        return $files;
    }

    private function rel(string $abs): string
    {
        return str_replace('\\', '/', str_replace($this->root . '/', '', $abs));
    }

    private function cardTable(string $cardCode): ?string
    {
        $rows = $this->schema['table.sql']['seeds'] ?? [];
        foreach ($rows as $s) {
            if (($s['table'] ?? '') !== 'card_modules') {
                continue;
            }
            $cols = $s['columns'] ?? [];
            $ci = array_search('code', $cols, true);
            $ti = array_search('table_name', $cols, true);
            if ($ci === false || $ti === false) {
                continue;
            }
            foreach (($s['rows'] ?? []) as $r) {
                $parts = explode('|', $r);
                if (($parts[$ci] ?? '') === $cardCode) {
                    $t = $parts[$ti] ?? '';
                    return $t !== '' ? $t : null;
                }
            }
        }
        // Fallback: form_card_<code> convention.
        return 'form_card_' . $cardCode;
    }

    /** @return array<string, array> columns of a table from table.sql model */
    private function columnsOf(string $table): array
    {
        return $this->schema['table.sql']['tables'][$table]['columns'] ?? [];
    }

    private function checkWriters(): void
    {
        $files = $this->phpFiles();
        foreach ($files as $abs) {
            $rel = $this->rel($abs);
            $src = file_get_contents($abs);
            if ($src === false) {
                continue;
            }
            $this->checkWriterFile($rel, $src);
        }
    }

    private function checkWriterFile(string $rel, string $src): void
    {
        $lines = explode("\n", $src);
        $lineOf = static function (int $offset) use ($lines): int {
            return substr_count(substr(implode("\n", $lines), 0, $offset), "\n") + 1;
        };

        // Determine card code from path cards/<code>/...
        $cardCode = null;
        if (preg_match('#^cards/([^/]+)/#', $rel, $m)) {
            $cardCode = $m[1];
        }
        $cardTable = $cardCode !== null ? $this->cardTable($cardCode) : null;

        // --- $data array style (W3, W4, W5, W6) ---
        $dataArrays = $this->extractDataArrays($src);
        foreach ($dataArrays as $da) {
            $line = $lineOf($da['offset']);
            $table = $cardTable;
            // Try to find the table from nearby INSERT INTO `...` text.
            $nearby = substr($src, $da['offset'], 6000);
            if (preg_match('/INSERT\s+INTO\s+`([^`]+)`/i', $nearby, $tm)) {
                $table = $tm[1];
            } elseif (preg_match('/UPDATE\s+`([^`]+)`/i', $nearby, $tm)) {
                $table = $tm[1];
            }
            if ($table === null) {
                $this->add($rel, $line, 'W3', 'UNRESOLVED', '$data array: cannot determine target table');
                continue;
            }
            $cols = $this->columnsOf($table);
            if (empty($cols) && $table !== 'card_documents') {
                // card_documents lives in upgrade_003; model from upgrades too.
                $cols = $this->schema['upgrades']['tables'][$table]['columns'] ?? $cols;
            }
            $keys = array_column($da['entries'], 'key');
            // Duplicate keys.
            $seen = [];
            foreach ($keys as $k) {
                if (isset($seen[$k])) {
                    $this->add($rel, $line, 'W3', 'ERROR', "Table `$table`: key '$k' listed twice in \$data array");
                }
                $seen[$k] = true;
            }
            foreach ($da['entries'] as $e) {
                if (!isset($cols[$e['key']])) {
                    $this->add($rel, $lineOf($da['offset'] + $e['offset']), 'W1', 'ERROR', "Table `$table`: column '{$e['key']}' does not exist");
                }
                // W6 variable checks.
                $this->checkVarUsage($rel, $src, $e, $table);
            }
            // W4: NOT NULL without default present.
            foreach ($cols as $cname => $c) {
                if ($c['not_null'] && $c['default'] === null && !$c['auto_increment'] && !in_array($cname, $keys, true)) {
                    $this->add($rel, $line, 'W4', 'ERROR', "Table `$table`: NOT NULL column `$cname` without DEFAULT is missing from INSERT (\$data array)");
                }
            }
            // Identity exclusion for UPDATE (only when the file actually updates).
            $doesUpdate = preg_match('/\bUPDATE\s+`[^`]+`\s+SET\b/i', $src) === 1;
            if ($doesUpdate && !str_contains($src, 'array_diff_key($data')) {
                $this->add($rel, $line, 'W3', 'ERROR', '$data array is used for UPDATE without excluding identity columns (no array_diff_key($data, ...))');
            } elseif ($doesUpdate && preg_match('/\$identityCols\s*=\s*\[([^\]]*)\]/', $src, $im)) {
                preg_match_all("/'([^']+)'/", $im[1], $ids);
                foreach ($keys as $k) {
                    if (in_array($k, ['id', 'pid', 'encounter', 'cardiology_id', 'created_at', 'activity', 'user'], true) && !in_array($k, $ids[1], true)) {
                        $this->add($rel, $line, 'W3', 'ERROR', "UPDATE does not exclude identity column '$k'");
                    }
                }
            }
        }

        // --- Explicit INSERT / UPDATE literals (W1, W2) ---
        $this->checkExplicitSql($rel, $src, $cardTable);

        // --- W5 calculated columns (save.php only) ---
        if ($cardTable !== null && str_ends_with($rel, 'save.php')) {
            $this->checkCalculated($rel, $src, $cardTable);
        }

        // --- W7 input names ---
        if ($cardCode !== null && str_ends_with($rel, 'save.php')) {
            $this->checkInputNames($rel, $src, $cardCode, $cardTable);
        }

        // --- W8 / W9 from RANGES ---
        if ($cardCode !== null && str_ends_with($rel, 'save.php')) {
            $this->checkRanges($rel, $src, $cardTable);
        }

        // --- W10 finalize logic columns ---
        if ($cardCode !== null && str_ends_with($rel, 'save.php')) {
            $this->checkFinalize($rel, $src, $cardTable);
        }
    }

    /**
     * Extract `$data = [ 'key' => expr, ... ];` blocks using token_get_all.
     *
     * @return array<int, array{offset: int, entries: array<int, array{key: string, var: ?string, offset: int}>}>
     */
    private function extractDataArrays(string $src): array
    {
        $tokens = token_get_all($src);
        $result = [];
        $n = count($tokens);
        // Compute offsets.
        $offsets = [];
        $pos = 0;
        foreach ($tokens as $i => $t) {
            $offsets[$i] = $pos;
            $pos += strlen(is_array($t) ? $t[1] : $t);
        }
        for ($i = 0; $i < $n; $i++) {
            $t = $tokens[$i];
            if (!is_array($t) || $t[0] !== T_VARIABLE || $t[1] !== '$data') {
                continue;
            }
            // Next non-whitespace must be '=' then '['.
            $j = $this->nextMeaningful($tokens, $i + 1);
            if ($j === null || $tokens[$j] !== '=') {
                continue;
            }
            $k = $this->nextMeaningful($tokens, $j + 1);
            if ($k === null || $tokens[$k] !== '[') {
                continue;
            }
            $entries = [];
            $depth = 1;
            $expectKey = true;
            for ($p = $k + 1; $p < $n && $depth > 0; $p++) {
                $tk = $tokens[$p];
                $txt = is_array($tk) ? $tk[1] : $tk;
                if ($txt === '[' || $txt === '(') {
                    $depth++;
                    continue;
                }
                if ($txt === ']' || $txt === ')') {
                    $depth--;
                    continue;
                }
                if ($depth === 1 && $expectKey && is_array($tk) && $tk[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $key = trim($tk[1], "'\"");
                    // Find => then the value's first variable.
                    $q = $this->nextMeaningful($tokens, $p + 1);
                    $var = null;
                    $varOffset = $offsets[$p];
                    if ($q !== null && is_array($tokens[$q]) && $tokens[$q][0] === T_DOUBLE_ARROW) {
                        $r = $this->nextMeaningful($tokens, $q + 1);
                        if ($r !== null && is_array($tokens[$r]) && $tokens[$r][0] === T_VARIABLE) {
                            $var = $tokens[$r][1];
                            $varOffset = $offsets[$r];
                        }
                    }
                    $entries[] = ['key' => $key, 'var' => $var, 'offset' => $varOffset];
                    $expectKey = false;
                } elseif ($depth === 1 && $txt === ',') {
                    $expectKey = true;
                }
            }
            if (!empty($entries)) {
                $result[] = ['offset' => $offsets[$i], 'entries' => $entries];
            }
        }
        return $result;
    }

    private function nextMeaningful(array $tokens, int $i): ?int
    {
        $n = count($tokens);
        for (; $i < $n; $i++) {
            $t = $tokens[$i];
            if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            return $i;
        }
        return null;
    }

    private function checkVarUsage(string $rel, string $src, array $entry, string $table): void
    {
        $var = $entry['var'] ?? null;
        if ($var === null || $var === '$this') {
            return;
        }
        $name = substr((string)$var, 1);
        // Assigned earlier in the same file?
        $usePos = $entry['offset'];
        $before = substr($src, 0, $usePos);
        $line = substr_count($before, "\n") + 1;
        if (!preg_match('/\$' . preg_quote($name, '/') . '\s*=/', $before)
            && !preg_match('/\$' . preg_quote($name, '/') . '\s*(\.=|\+\=|-=|\*=|\/=)/', $before)
            && !preg_match('/foreach\s*\([^)]*\$' . preg_quote($name, '/') . '\b/', $before)
            && !preg_match('/function\s+\w*\s*\([^)]*\$' . preg_quote($name, '/') . '\b/', $before)
            && !preg_match('/catch\s*\([^)]*\$' . preg_quote($name, '/') . '\b/', $before)
            && !preg_match('/list\s*\([^)]*\$' . preg_quote($name, '/') . '\b/', $before)) {
            $this->add($rel, $line, 'W6', 'ERROR', "Variable \$$name used for column '{$entry['key']}' is never assigned earlier in this file");
        }
        // Naming: camelCase(column).
        $expected = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $entry['key']))));
        if ($name !== $expected && !str_starts_with($name, substr($expected, 0, 4)) && $name !== 'data') {
            // Only warn for close cases to avoid noise: different name entirely.
            $this->add($rel, $line, 'W6', 'WARN', "Variable \$$name does not follow column name '{$entry['key']}' (expected \$$expected)");
        }
    }

    private function checkExplicitSql(string $rel, string $src, ?string $cardTable): void
    {
        // Find sqlInsert/sqlStatement/sqlQuery calls with a single-quoted SQL literal.
        if (!preg_match_all('/(sqlInsert|sqlStatement|sqlQuery)\s*\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*(?:,([^;\n]*))?/s', $src, $m, PREG_OFFSET_CAPTURE)) {
            return;
        }
        foreach ($m[0] as $idx => $full) {
            $sql = stripcslashes($m[2][$idx][0]);
            $offset = $full[1];
            $line = substr_count(substr($src, 0, $offset), "\n") + 1;
            $args = trim($m[3][$idx][0] ?? '');
            if (!preg_match('/\b(INSERT\s+INTO|UPDATE)\s+`([^`]+)`/i', $sql, $tm)) {
                continue;
            }
            $table = $tm[2];
            $cols = $this->columnsOf($table);
            if (empty($cols)) {
                $cols = $this->schema['upgrades']['tables'][$table]['columns'] ?? $cols;
            }
            if (empty($cols)) {
                $this->add($rel, $line, 'W1', 'UNRESOLVED', "Cannot verify columns of `$table` (not in schema model)");
                continue;
            }
            $isInsert = stripos($tm[1], 'INSERT') !== false;
            if ($isInsert) {
                if (preg_match('/INSERT\s+INTO\s+`[^`]+`\s*\(([^)]*)\)/i', $sql, $cm)) {
                    $colList = array_map(static fn($c) => trim(trim($c, " \t`"), " \t\r\n"), explode(',', $cm[1]));
                    foreach ($colList as $c) {
                        if (!isset($cols[$c])) {
                            $this->add($rel, $line, 'W1', 'ERROR', "Table `$table`: column `$c` does not exist");
                        }
                    }
                    // W2: columns vs value slots vs placeholders vs params.
                    $slots = 0;
                    $placeholders = 0;
                    $valuesPos = stripos($sql, 'VALUES');
                    if ($valuesPos !== false) {
                        $open = strpos($sql, '(', $valuesPos);
                        if ($open !== false) {
                            $close = self::matchingParen($sql, $open);
                            if ($close !== false) {
                                $slotList = self::splitTopLevel(substr($sql, $open + 1, $close - $open - 1));
                                $slots = count($slotList);
                                foreach ($slotList as $s) {
                                    if (str_contains($s, '?')) {
                                        $placeholders += substr_count($s, '?');
                                    }
                                }
                            }
                        }
                    }
                    $paramCount = null;
                    if (preg_match('/^\[(.*)\]$/s', $args, $am)) {
                        $paramCount = $this->countArrayElements($am[1]);
                    }
                    if ($slots !== count($colList) || ($paramCount !== null && $placeholders !== $paramCount)) {
                        $this->add($rel, $line, 'W2', 'ERROR', "Table `$table`: INSERT has " . count($colList) . " columns, $slots value slots, $placeholders placeholders, " . var_export($paramCount, true) . ' parameters');
                    }
                } else {
                    $this->add($rel, $line, 'W2', 'UNRESOLVED', "INSERT into `$table` without explicit column list (cannot verify counts)");
                }
            } else {
                // UPDATE: SET columns + WHERE columns.
                $named = [];
                if (preg_match_all('/`([a-z_0-9]+)`\s*=/i', $sql, $sm)) {
                    $named = array_unique($sm[1]);
                }
                if (preg_match_all('/(?:WHERE|AND|OR)\s+`([a-z_0-9]+)`\s*(?:=|<>|>|<|>=|<=|IS|IN)/i', $sql, $wm)) {
                    $named = array_unique(array_merge($named, $wm[1]));
                }
                foreach ($named as $c) {
                    if (!isset($cols[$c])) {
                        $this->add($rel, $line, 'W1', 'ERROR', "Table `$table`: column `$c` does not exist");
                    }
                }
                // W2 for UPDATE: placeholders vs params when literal array.
                $placeholders = substr_count($sql, '?');
                $paramCount = null;
                if (preg_match('/^\[(.*)\]$/s', $args, $am)) {
                    $paramCount = $this->countArrayElements($am[1]);
                }
                if ($paramCount !== null && $placeholders !== $paramCount) {
                    $this->add($rel, $line, 'W2', 'ERROR', "Table `$table`: UPDATE has $placeholders placeholders but " . var_export($paramCount, true) . ' parameters');
                }
            }
        }
    }

    private function countArrayElements(string $inner): ?int
    {
        $inner = trim($inner);
        if ($inner === '') {
            return 0;
        }
        // Count top-level commas: elements = commas + 1 (no trailing comma handling needed for our style).
        $parts = self::splitTopLevel($inner);
        return count($parts);
    }

    private function checkCalculated(string $rel, string $src, string $table): void
    {
        $cols = $this->columnsOf($table);
        foreach ($cols as $cname => $c) {
            if (!str_contains(strtolower($c['comment'] ?? ''), 'calculated')) {
                continue;
            }
            // Assigned in $data or INSERT?
            $assigned = preg_match("/'" . preg_quote($cname, '/') . "'\s*=>/", $src) === 1
                || str_contains($src, "`$cname`");
            if (!$assigned) {
                $this->add($rel, 1, 'W5', 'ERROR', "Calculated column `$table`.`$cname` is never assigned by save.php");
            }
            // Read from POST?
            foreach (['$_POST', '$_REQUEST'] as $g) {
                if (preg_match('/' . preg_quote($g, '/') . '\s*\[\s*.' . preg_quote($cname, '/') . '.\s*\]/', $src)) {
                    $this->add($rel, 1, 'W5', 'WARN', "Calculated column `$table`.`$cname` is read from POST");
                }
            }
            // ...or via a $p variable built from $_POST (the save.php convention).
            if (preg_match('/\$p\s*=\s*[^;]*\$_POST/s', $src) && preg_match('/\$p\s*\[\s*.' . preg_quote($cname, '/') . '.\s*\]/', $src)) {
                $this->add($rel, 1, 'W5', 'WARN', "Calculated column `$table`.`$cname` is read from POST");
            }
        }
    }

    /** @return array<string, true> input names (prefix before '[') */
    private function inputNames(string $cardCode, ?string $table = null): array
    {
        $names = [];
        $patterns = [];
        $cardCols = $table !== null ? $this->columnsOf($table) : [];
        $dirs = [$this->root . '/cards/' . $cardCode];
        foreach ($dirs as $d) {
            if (!is_dir($d)) {
                continue;
            }
            foreach (['new.php', '_form_fields.php'] as $f) {
                $p = $d . '/' . $f;
                if (!is_file($p)) {
                    continue;
                }
                $src = file_get_contents($p);
                if (preg_match_all('/name="([^"]+)"/', $src, $m)) {
                    foreach ($m[1] as $n) {
                        $names[explode('[', $n)[0]] = true;
                        // Loop-generated names like serology_<i>_type: record a
                        // pattern to match columns later.
                        if (str_contains($n, '<?php')) {
                            $pat = preg_quote($n, '/');
                            $pat = str_replace(preg_quote('<?php echo $i; ?>', '/'), '\d+', $pat);
                            $pat = str_replace(preg_quote('<?php echo $i?>', '/'), '\d+', $pat);
                            $patterns[] = '/^' . $pat . '$/';
                        }
                    }
                }
                if (preg_match_all("/name='([^']+)'/", $src, $m)) {
                    foreach ($m[1] as $n) {
                        $names[explode('[', $n)[0]] = true;
                    }
                }
                // Field helpers (chagasSelect('field', ...), loops whose input name
                // is emitted from a variable fed by an array of field names):
                // any quoted identifier that is a column of the card table and
                // appears as a function-call first argument counts.
                if (preg_match_all("/\(\s*'([a-z_0-9]+)'\s*,/", $src, $m)) {
                    foreach ($m[1] as $n) {
                        if (isset($cardCols[$n])) {
                            $names[$n] = true;
                        }
                    }
                }
                // Dynamic helper names: chagasSelect('serology_' . $i . '_type', ...)
                if (preg_match_all("/'([a-z_0-9]+)_'\s*\\.\s*\\\$i\s*\\.\s*'([a-z_0-9]+)'/", $src, $m)) {
                    foreach ($m[1] as $idx2 => $pre) {
                        $suf = $m[2][$idx2] ?? '';
                        foreach (array_keys($cardCols) as $col) {
                            if (preg_match('/^' . preg_quote($pre, '/') . '_\d+' . preg_quote($suf, '/') . '$/', $col)) {
                                $names[$col] = true;
                            }
                        }
                    }
                }
                // Loop field arrays: ['lvedd_mm', 'LVEDD (mm)', ...] entries.
                if (!empty($cardCols) && preg_match_all("/'([a-z_0-9]+)'\s*,/", $src, $m)) {
                    foreach ($m[1] as $n) {
                        if (isset($cardCols[$n])) {
                            $names[$n] = true;
                        }
                    }
                }
            }
        }
        // templates/_*.php shared partials
        $tpl = $this->root . '/templates';
        if (is_dir($tpl)) {
            foreach (glob($tpl . '/*.php') ?: [] as $p) {
                $src = file_get_contents($p);
                if (preg_match_all('/name="([^"]+)"/', $src, $m)) {
                    foreach ($m[1] as $n) {
                        $names[explode('[', $n)[0]] = true;
                        // Loop-generated names like serology_<i>_type: record a
                        // pattern to match columns later.
                        if (str_contains($n, '<?php')) {
                            $pat = preg_quote($n, '/');
                            $pat = str_replace(preg_quote('<?php echo $i; ?>', '/'), '\d+', $pat);
                            $pat = str_replace(preg_quote('<?php echo $i?>', '/'), '\d+', $pat);
                            $patterns[] = '/^' . $pat . '$/';
                        }
                    }
                }
            }
        }
        // Expand loop-generated name patterns against the card columns.
        foreach ($patterns as $pat) {
            foreach (array_keys($cardCols) as $col) {
                if (preg_match($pat, $col)) {
                    $names[$col] = true;
                }
            }
        }
        return $names;
    }

    private function checkInputNames(string $rel, string $src, string $cardCode, ?string $table): void
    {
        $inputs = $this->inputNames($cardCode, $table);
        // POST keys read: $_POST['x'], $_REQUEST['x'], $p['x'] (when $p built from $_POST).
        $reads = [];
        foreach (['$_POST', '$_REQUEST'] as $g) {
            if (preg_match_all('/' . preg_quote($g, '/') . '\s*\[\s*\'([a-z_0-9]+)\'\s*\]/', $src, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[1] as [$key, $off]) {
                    $reads[$key] = substr_count(substr($src, 0, $off), "\n") + 1;
                }
            }
        }
        // $p['x'] reads.
        if (preg_match_all('/\$p\s*\[\s*\'([a-z_0-9]+)\'\s*\]/', $src, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[1] as [$key, $off]) {
                $reads[$key] = substr_count(substr($src, 0, $off), "\n") + 1;
            }
        }
        $system = ['csrf_token_form' => true, 'pid' => true, 'encounter' => true, 'cardiology_id' => true, 'study_id' => true, 'action' => true, 'field' => true];
        foreach ($reads as $key => $line) {
            if (isset($system[$key])) {
                continue;
            }
            if (!isset($inputs[$key])) {
                $this->add($rel, $line, 'W7', 'ERROR', "POST key '$key' is read by save.php but no input named '$key' exists in the card form");
            }
        }
        // WARN: fillable columns with no input and never read.
        if ($table !== null) {
            $cols = $this->columnsOf($table);
            $calculated = [];
            foreach ($cols as $cname => $c) {
                if (str_contains(strtolower($c['comment'] ?? ''), 'calculated')) {
                    $calculated[$cname] = true;
                }
            }
            $systemCols = ['id' => true, 'pid' => true, 'encounter' => true, 'cardiology_id' => true, 'activity' => true, 'authorized' => true, 'groupname' => true, 'user' => true, 'status' => true, 'finalized_at' => true, 'finalized_by' => true, 'created_at' => true, 'updated_at' => true, 'created_by' => true, 'date' => true];
            foreach ($cols as $cname => $c) {
                if (isset($systemCols[$cname]) || isset($calculated[$cname])) {
                    continue;
                }
                if (!isset($inputs[$cname]) && !isset($reads[$cname])) {
                    $this->add($rel, 1, 'W7', 'WARN', "Column `$table`.`$cname` has no form input and is never read by save.php (user cannot fill it)");
                }
            }
        }
    }

    /** Parse RANGES constant entries: 'field' => [min, max] */
    private function parseRanges(string $src): array
    {
        if (!preg_match('/const\s+RANGES\s*=\s*\[(.*?)\];/s', $src, $m)) {
            return [];
        }
        $ranges = [];
        if (preg_match_all("/'([a-z_0-9]+)'\s*=>\s*\[\s*(-?[\d.]+)\s*,\s*(-?[\d.]+)\s*\]/", $m[1], $mm, PREG_SET_ORDER)) {
            foreach ($mm as $e) {
                $ranges[$e[1]] = [(float)$e[2], (float)$e[3]];
            }
        }
        return $ranges;
    }

    private function checkRanges(string $rel, string $src, ?string $table): void
    {
        if ($table === null) {
            return;
        }
        $cols = $this->columnsOf($table);
        $ranges = $this->parseRanges($src);
        foreach ($ranges as $field => [$min, $max]) {
            if (!isset($cols[$field])) {
                $this->add($rel, 1, 'W8', 'ERROR', "RANGES key '$field' is not a column of `$table`");
                continue;
            }
            $c = $cols[$field];
            // W9 capacity.
            $cap = self::columnCapacity($c);
            if ($cap !== null) {
                [$capMin, $capMax] = $cap;
                if ($min < $capMin || $max > $capMax) {
                    $this->add($rel, 1, 'W9', 'ERROR', "RANGES for `$table`.`$field` [$min, $max] overflows column {$c['type']} (capacity [$capMin, $capMax])");
                }
            }
        }
        // W8 list-backed columns.
        foreach ($cols as $cname => $c) {
            if (!preg_match('/list_id\s+([a-z_0-9]+)/i', $c['comment'] ?? '', $lm)) {
                continue;
            }
            $listId = $lm[1];
            // Validated with CardInput::listOption('<listId>', ..., '<field>'?
            // Also accept dynamic names: 'serology_' . $i . '_type' for serology_N_type.
            $validated = preg_match("/CardInput::listOption\\(\\s*'" . preg_quote($listId, '/') . "'\\s*,\\s*[^,]+,\\s*'" . preg_quote($cname, '/') . "'/", $src) === 1;
            if (!$validated && preg_match('/^([a-z_]+)_(\d+)_(.*)$/', $cname, $dm)) {
                $validated = preg_match(
                    "/CardInput::listOption\\(\\s*'" . preg_quote($listId, '/') . "'\\s*,\\s*[^,]+,\\s*'"
                    . preg_quote($dm[1], '/') . "_'\\s*\\.\\s*\\$\\w+\\s*\\.\\s*'"
                    . preg_quote('_' . $dm[3], '/') . "'/",
                    $src
                ) === 1;
            }
            if (!$validated) {
                $this->add($rel, 1, 'W8', 'ERROR', "List-backed column `$table`.`$cname` (list_id $listId) is not validated with CardInput::listOption('$listId', ..., '$cname')");
            }
            // Registered in list_options seeds with list_id 'lists'.
            $registered = false;
            foreach ($this->schema['table.sql']['seeds'] ?? [] as $s) {
                if (($s['table'] ?? '') !== 'list_options') {
                    continue;
                }
                foreach (($s['rows'] ?? []) as $r) {
                    $parts = explode('|', $r);
                    if (($parts[0] ?? '') === 'lists' && ($parts[1] ?? '') === $listId) {
                        $registered = true;
                        break 2;
                    }
                }
            }
            if (!$registered) {
                $this->add($rel, 1, 'W8', 'ERROR', "List id '$listId' for `$table`.`$cname` has no registration row (list_id='lists') in table.sql seeds");
            }
            // Longest option_id fits column length.
            if (preg_match('/^varchar\((\d+)\)$/i', $c['type'], $vm)) {
                $maxLen = (int)$vm[1];
                $longest = 0;
                foreach ($this->schema['table.sql']['seeds'] ?? [] as $s) {
                    if (($s['table'] ?? '') !== 'list_options') {
                        continue;
                    }
                    foreach (($s['rows'] ?? []) as $r) {
                        $parts = explode('|', $r);
                        if (($parts[0] ?? '') === $listId) {
                            $longest = max($longest, strlen($parts[1] ?? ''));
                        }
                    }
                }
                if ($longest > $maxLen) {
                    $this->add($rel, 1, 'W8', 'ERROR', "Longest option_id of list '$listId' ($longest chars) does not fit `$table`.`$cname` VARCHAR($maxLen)");
                }
            }
        }
    }

    /**
     * Capacity [min, max] for numeric columns, accounting for the 3 decimals
     * CardInput::decimal writes (number_format 3) for DECIMAL columns and the
     * integer range for integer types. Null when not numeric.
     *
     * @return array{0: float, 1: float}|null
     */
    public static function columnCapacity(array $c): ?array
    {
        $t = strtolower($c['type']);
        if (preg_match('/^decimal\((\d+),(\d+)\)$/', $t, $m)) {
            [, $p, $s] = $m;
            $p = (int)$p;
            $s = (int)$s;
            $intDigits = max(0, $p - $s);
            $max = (float)(str_repeat('9', $intDigits) . ($s > 0 ? '.' . str_repeat('9', $s) : ''));
            return [-$max, $max];
        }
        $ints = [
            'tinyint' => [-128, 127], 'tinyint(1)' => [-128, 127],
            'smallint' => [-32768, 32767], 'int' => [-2147483648, 2147483647],
            'bigint' => [-9223372036854775808, 9223372036854775807],
        ];
        if (isset($ints[$t])) {
            return $ints[$t];
        }
        if (str_ends_with($t, ' unsigned')) {
            $base = trim(str_replace(' unsigned', '', $t));
            if (isset($ints[$base])) {
                return [0, $ints[$base][1] * 2 + 1];
            }
        }
        return null;
    }

    private function checkFinalize(string $rel, string $src, ?string $table): void
    {
        if ($table === null) {
            return;
        }
        $cols = $this->columnsOf($table);
        // Columns named inside the finalize block: the save_final action gate.
        if (!preg_match_all('/if\s*\(\s*(?:\$status\s*===\s*\'final\'|\$action\s*===\s*\'save_final\')[^\{]*\{(.*?)\n\s*\}/s', $src, $m, PREG_OFFSET_CAPTURE)) {
            return;
        }
        foreach ($m[1] as [$block, $off]) {
            if (preg_match_all('/\$p\s*\[\s*\'([a-z_0-9]+)\'\s*\]/', $block, $pm)) {
                foreach (array_unique($pm[1]) as $field) {
                    if (!isset($cols[$field])) {
                        $line = substr_count(substr($src, 0, $off), "\n") + 1;
                        $this->add($rel, $line, 'W10', 'ERROR', "Finalize logic references column '$field' which is not a column of `$table`");
                    }
                }
            }
        }
    }

    // ------------------------------------------------------------------
    // R rules: readers
    // ------------------------------------------------------------------
    private function checkReaders(): void
    {
        $files = $this->phpFiles();
        foreach ($files as $abs) {
            $rel = $this->rel($abs);
            $src = file_get_contents($abs);
            if ($src === false) {
                continue;
            }
            $this->checkReaderFile($rel, $src);
        }
        $this->checkChartsFiles();
        $this->checkCardSeeds();
    }

    private function checkReaderFile(string $rel, string $src): void
    {
        $cardCode = null;
        if (preg_match('#^cards/([^/]+)/#', $rel, $m)) {
            $cardCode = $m[1];
        }
        $cardTable = $cardCode !== null ? $this->cardTable($cardCode) : null;

        // R1: SELECT columns in SQL strings.
        if (preg_match_all('/sql(Query|Statement)\s*\(\s*\'([^\']*SELECT[^\']*)\'/is', $src, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[2] as [$sql, $off]) {
                $line = substr_count(substr($src, 0, $off), "\n") + 1;
                if (preg_match('/FROM\s+`([^`]+)`/i', $sql, $fm)) {
                    $table = $fm[1];
                    $cols = $this->columnsOf($table);
                    if (empty($cols)) {
                        $cols = $this->schema['upgrades']['tables'][$table]['columns'] ?? $cols;
                    }
                    if (empty($cols)) {
                        continue;
                    }
                    // Column list between SELECT and FROM.
                    if (preg_match('/SELECT\s+(.+?)\s+FROM\s+`' . preg_quote($table, '/') . '`/is', $sql, $cm)) {
                        $list = $cm[1];
                        if (trim($list) === '*') {
                            continue;
                        }
                        foreach (self::splitTopLevel($list) as $item) {
                            $item = trim($item);
                            if (preg_match('/^`([a-z_0-9]+)`(?:\s+AS\s+`?([a-z_0-9]+)`?)?$/i', $item, $im)) {
                                $col = strtolower($im[1]);
                                if (!isset($cols[$col]) && !isset($cols[$im[1]])) {
                                    $this->add($rel, $line, 'R1', 'ERROR', "Table `$table`: SELECT column `$col` does not exist");
                                }
                            } elseif (preg_match('/^`([a-z_0-9]+)`\./', $item)) {
                                // Qualified column; skip detailed check.
                                continue;
                            } elseif (preg_match('/^[a-z_0-9]+$/i', $item)) {
                                $col = strtolower($item);
                                if (!isset($cols[$col]) && !isset($cols[$item])) {
                                    $this->add($rel, $line, 'R1', 'ERROR', "Table `$table`: SELECT column `$col` does not exist");
                                }
                            }
                        }
                    }
                }
            }
        }

        // R2: $study['x'] / $row['x'] keys.
        if ($cardTable !== null && ($cardTable = $this->columnsOf($cardTable) ? $cardTable : $cardTable)) {
            $cols = $this->columnsOf($cardTable);
            if (!empty($cols)) {
                $aliases = $this->queryAliases($src);
                $known = array_merge(array_keys($cols), $aliases, ['patient_name', 'patient_dob', 'patient_sex', 'patient_dni', 'patient_age', 'encounter_label', 'batch_date_formatted', 'report_title']);
                if (str_contains($src, 'list_options')) {
                    $known = array_merge($known, ['list_id', 'option_id', 'title', 'seq', 'is_default', 'option_value', 'activity']);
                }
                if (preg_match_all('/\$(?:study|row|views)\s*\[\s*\'([a-z_0-9]+)\'\s*\]/', $src, $m, PREG_OFFSET_CAPTURE)) {
                    foreach ($m[1] as [$key, $off]) {
                        if (!in_array($key, $known, true)) {
                            $line = substr_count(substr($src, 0, $off), "\n") + 1;
                            $this->add($rel, $line, 'R2', 'WARN', "Key '$key' read from study row is not a column of `" . $this->cardTable($cardCode) . '`');
                        }
                    }
                }
            }
        }
    }

    /** @return string[] aliases defined via `col` AS `alias` in SELECTs */
    private function queryAliases(string $src): array
    {
        $aliases = [];
        if (preg_match_all('/SELECT\s+(.+?)\s+FROM/is', $src, $m)) {
            foreach ($m[1] as $list) {
                if (preg_match_all('/AS\s+`?([a-z_0-9]+)`?/i', $list, $am)) {
                    foreach ($am[1] as $a) {
                        $aliases[] = $a;
                    }
                }
            }
        }
        return $aliases;
    }

    private function checkChartsFiles(): void
    {
        foreach (glob($this->root . '/cards/*/charts.php') ?: [] as $abs) {
            $rel = $this->rel($abs);
            $cardCode = basename(dirname($abs));
            $table = $this->cardTable($cardCode);
            $cols = $table !== null ? $this->columnsOf($table) : [];
            $src = file_get_contents($abs);
            if (!preg_match_all("/'field'\s*=>\s*'([a-z_0-9]+)'/", $src, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            // Stage table for 'study' charts: parse stage_table from same file.
            $stageTable = null;
            if (preg_match("/'stage_table'\s*=>\s*'([a-z_0-9]+)'/", $src, $sm)) {
                $stageTable = $sm[1];
            }
            foreach ($m[1] as [$field, $off]) {
                $line = substr_count(substr($src, 0, $off), "\n") + 1;
                $col = $cols[$field] ?? null;
                if ($col === null && $stageTable !== null) {
                    $col = $this->columnsOf($stageTable)[$field] ?? null;
                }
                if ($col === null) {
                    $this->add($rel, $line, 'R3', 'ERROR', "Chart field '$field' is not a column of the card table" . ($stageTable ? " or stage table `$stageTable`" : ''));
                    continue;
                }
                $t = strtolower($col['type']);
                if (!preg_match('/^(decimal|int|smallint|tinyint|bigint|float|double)/', $t)) {
                    $this->add($rel, $line, 'R3', 'WARN', "Chart field '$field' is not numeric ({$col['type']})");
                }
            }
        }
    }

    private function checkCardSeeds(): void
    {
        $codes = [];
        foreach (($this->schema['table.sql']['seeds'] ?? []) as $s) {
            if (($s['table'] ?? '') !== 'card_modules') {
                continue;
            }
            $cols = $s['columns'] ?? [];
            $ci = array_search('code', $cols, true);
            $ti = array_search('table_name', $cols, true);
            $di = array_search('doc_category_id', $cols, true);
            $tyi = array_search('card_type', $cols, true);
            foreach (($s['rows'] ?? []) as $r) {
                $parts = explode('|', $r);
                $code = $parts[$ci] ?? '';
                $table = $parts[$ti] ?? '';
                $docCat = $di !== false ? ($parts[$di] ?? '') : '';
                $type = $tyi !== false ? ($parts[$tyi] ?? 'study') : 'study';
                $codes[$code] = ['table' => $table, 'doc_cat' => $docCat, 'type' => $type, 'line' => $s['line'] ?? 1];
            }
        }
        // Categories seed ids.
        $catIds = [];
        foreach (($this->schema['table.sql']['seeds'] ?? []) as $s) {
            if (($s['table'] ?? '') !== 'categories') {
                continue;
            }
            $cols = $s['columns'] ?? [];
            $ii = array_search('id', $cols, true);
            foreach (($s['rows'] ?? []) as $r) {
                $parts = explode('|', $r);
                if ($ii !== false && isset($parts[$ii])) {
                    $catIds[$parts[$ii]] = true;
                }
            }
        }
        foreach ($codes as $code => $info) {
            $dir = $this->root . '/cards/' . $code;
            if (!is_dir($dir)) {
                $this->add('table.sql', $info['line'], 'R4', 'ERROR', "Seeded card '$code' has no folder cards/$code/");
                continue;
            }
            if ($info['type'] !== 'tool') {
                foreach (['new.php', 'view.php', 'save.php', 'print.php'] as $f) {
                    if (!is_file($dir . '/' . $f)) {
                        $this->add('table.sql', $info['line'], 'R4', 'ERROR', "Seeded card '$code' is missing cards/$code/$f");
                    }
                }
            }
            if ($info['table'] !== '' && !isset($this->schema['table.sql']['tables'][$info['table']])) {
                $this->add('table.sql', $info['line'], 'R4', 'ERROR', "Seeded card '$code' table_name '{$info['table']}' does not exist in table.sql");
            }
            if ($info['doc_cat'] !== '' && !isset($catIds[$info['doc_cat']])) {
                $this->add('table.sql', $info['line'], 'R4', 'ERROR', "Seeded card '$code' doc_category_id {$info['doc_cat']} has no matching categories seed");
            }
        }
    }

    // ------------------------------------------------------------------
    // Report
    // ------------------------------------------------------------------
    public function report(string $format): string
    {
        $sevRank = ['ERROR' => 0, 'WARN' => 1, 'UNRESOLVED' => 2, 'UNPARSED' => 3];
        $findings = $this->findings;
        usort($findings, static function (SchemaFinding $a, SchemaFinding $b) use ($sevRank) {
            $d = ($sevRank[$a->severity] ?? 9) <=> ($sevRank[$b->severity] ?? 9);
            if ($d !== 0) {
                return $d;
            }
            $d = strcmp($a->file, $b->file);
            if ($d !== 0) {
                return $d;
            }
            return $a->line <=> $b->line;
        });

        $counts = [];
        foreach ($findings as $f) {
            $counts[$f->rule . '/' . $f->severity] = ($counts[$f->rule . '/' . $f->severity] ?? 0) + 1;
        }

        $out = "# Schema audit report\n\n";
        $out .= "Generated by tools/check_schema.php. Rules: W=writers, R=readers, S=schema vs upgrades, PARSE=parser.\n\n";
        $out .= "## Summary\n\n| Rule | Severity | Count |\n|---|---|---|\n";
        $ruleCounts = [];
        foreach ($findings as $f) {
            $ruleCounts[$f->rule . '|' . $f->severity] = ($ruleCounts[$f->rule . '|' . $f->severity] ?? 0) + 1;
        }
        ksort($ruleCounts);
        foreach ($ruleCounts as $k => $c) {
            [$rule, $sev] = explode('|', $k, 2);
            $out .= "| $rule | $sev | $c |\n";
        }
        $totalErrors = 0;
        foreach ($ruleCounts as $k => $c) {
            if (str_ends_with($k, '|ERROR')) {
                $totalErrors += $c;
            }
        }
        $out .= "\nTotal ERRORs: $totalErrors\n\n## Findings\n\n";
        $currentFile = null;
        foreach ($findings as $f) {
            if ($f->file !== $currentFile) {
                $currentFile = $f->file;
                $out .= "### {$f->file}\n\n";
            }
            $out .= "- {$f->file}:{$f->line}  {$f->rule}  {$f->severity}  {$f->message}\n";
        }
        $out .= "\n## Not verified\n\n";
        $notVerified = array_filter($findings, static fn($f) => in_array($f->severity, ['UNRESOLVED', 'UNPARSED'], true));
        if (empty($notVerified)) {
            $out .= "None.\n";
        } else {
            $out .= 'Count: ' . count($notVerified) . "\n\n";
            foreach ($notVerified as $f) {
                $out .= "- {$f->file}:{$f->line}  {$f->rule}  {$f->severity}  {$f->message}\n";
            }
        }
        return $format === 'text' ? $out : $out;
    }
}

// ----------------------------------------------------------------------
// CLI
// ----------------------------------------------------------------------
function schemaCheckerMain(array $argv): int
{
    $root = dirname(__DIR__);
    $only = [];
    $format = 'md';
    $noLive = false;
    $out = $root . '/docs/schema_audit.md';
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--root=')) {
            $root = substr($arg, 7);
        } elseif (str_starts_with($arg, '--only=')) {
            $only[] = substr($arg, 7);
        } elseif (str_starts_with($arg, '--format=')) {
            $format = substr($arg, 9);
        } elseif ($arg === '--no-live') {
            $noLive = true;
        } elseif (str_starts_with($arg, '--out=')) {
            $out = substr($arg, 6);
        } elseif ($arg === '--no-report') {
            $out = '';
        }
    }
    $checker = new SchemaChecker($root, $only, $noLive);
    $code = $checker->run();
    $report = $checker->report($format);
    echo $report . PHP_EOL;
    if ($out !== '') {
        $dir = dirname($out);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($out, $report);
    }
    return $code;
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === realpath(__FILE__)) {
    exit(schemaCheckerMain($argv));
}
