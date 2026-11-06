<?php
/**
 * audit_prints.php - Print label audit (labels versus values)
 *
 * For every cards/<code>/print.php, extracts each label/value pair rendered
 * outside the generic sections (patient box and card-specific blocks), maps
 * the value to its source column(s), and flags label problems:
 *
 *   MISMATCH          label and column COMMENT share no meaningful word
 *   NO-UNIT           numeric column with a unit in its COMMENT but none in
 *                     the label
 *   DUPLICATE         same column printed twice with different labels
 *   PARTIAL           only one of a related column group is printed
 *   LABEL-NOT-IN-FORM printed label is not the xl() string the form uses
 *                     for that column
 *
 * Writes docs/print_label_audit.md and exits 1 when any MISMATCH or
 * DUPLICATE flag remains (so tools/run_checks.sh fails).
 *
 * Usage: php tools/audit_prints.php [--root=<dir>] [--out=<file>]
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/tools/check_schema.php';

final class PrintLabelAudit
{
    private string $root;

    /** Related column groups for PARTIAL detection. */
    private const GROUPS = [
        ['kuschnir_stage', 'abcd_stage'],
        ['sbp_day', 'sbp_night'],
        ['dbp_day', 'dbp_night'],
        ['hr_day', 'hr_night'],
    ];

    private const STOP = [
        'the', 'a', 'an', 'of', 'in', 'on', 'for', 'and', 'or', 'to', 'is', 'are',
        'date', 'time', 'datetime', 'study', 'value', 'values', 'field',
    ];

    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/\\');
    }

    private function schemaColumns(string $table): array
    {
        $parsed = SchemaChecker::parseSqlFile($this->root . '/table.sql');
        foreach ($parsed['tables'] as $t) {
            if ($t['name'] === $table) {
                return $t['columns'];
            }
        }
        return [];
    }

    private function cardTable(string $card): string
    {
        return $card === 'settings' ? 'card_settings' : 'form_card_' . $card;
    }

    /** Map column => form label xl() string for one card. */
    private function formLabels(string $card): array
    {
        $labels = [];
        foreach (['new.php', '_form_fields.php'] as $f) {
            $p = $this->root . '/cards/' . $card . '/' . $f;
            if (!is_file($p)) {
                continue;
            }
            $src = file_get_contents($p);
            // <label for="col"> ... xlt('Label')
            if (preg_match_all('/<label[^>]*for="([a-z_0-9]+)"[^>]*>(.*?)<\/label>/s', $src, $m, PREG_SET_ORDER)) {
                foreach ($m as $mm) {
                    if (preg_match("/xlt\\('([^']+)'\\)/", $mm[2], $lm)) {
                        $labels[$mm[1]] = $lm[1];
                    }
                }
            }
        }
        return $labels;
    }

    /** @return string[] meaningful words of a text */
    private function words(string $text): array
    {
        $words = preg_split('/[^a-z]+/i', strtolower($text)) ?: [];
        return array_values(array_filter($words, static fn($w) => strlen($w) > 2 && !in_array($w, self::STOP, true)));
    }

    public function run(): int
    {
        $rows = []; // per card: list of [label, columns, comment, unit, flags]
        $fail = false;
        $skip = $this->genericSkip();

        foreach ($this->studyCards() as $card) {
            $abs = $this->root . '/cards/' . $card . '/print.php';
            $table = $this->cardTable($card);
            $cols = $this->schemaColumns($table);
            $src = file_get_contents($abs);
            $formLabels = $this->formLabels($card);

            // Variable => column assignments ($var = $study['col'] ...).
            $varCol = [];
            if (preg_match_all('/\$(\w+)\s*=\s*(?:\(int\)|\(float\))?\s*\(?\s*\$study\[\'([a-z_0-9]+)\'\]/', $src, $m, PREG_SET_ORDER)) {
                foreach ($m as $mm) {
                    $varCol[$mm[1]] = [$mm[2]];
                }
            }
            // Direct $study['col'] reads in HTML.
            if (preg_match_all('/\$study\[\'([a-z_0-9]+)\'\]/', $src, $m)) {
                foreach ($m[1] as $c) {
                    $varCol['$study:' . $c] = [$c];
                }
            }

            $cardRows = [];
            // Label/value pairs: each <td class="label"> paired with the next
            // <td class="val"> inside the same <tr>.
            if (preg_match_all('/<tr>(.*?)<\/tr>/s', $src, $tm, PREG_SET_ORDER)) {
                foreach ($tm as $trm) {
                    $cells = [];
                    if (preg_match_all('/<td class="(label|val)"[^>]*>(.*?)<\/td>/s', $trm[1], $cm, PREG_SET_ORDER)) {
                        foreach ($cm as $c) {
                            $cells[] = [$c[1], $c[2]];
                        }
                    }
                    $pendingLabel = null;
                    foreach ($cells as [$kind, $html]) {
                        if ($kind === 'label') {
                            if (preg_match("/xlt\\('([^']+)'\\)/", $html, $lm)) {
                                $pendingLabel = $lm[1];
                            }
                            continue;
                        }
                        if ($pendingLabel === null) {
                            continue;
                        }
                        $columns = $this->columnsInExpr($html, $varCol, $src);
                        // Skip rows fed only by virtual keys (patient/encounter header).
                        $columns = array_values(array_filter($columns, static fn($c) => !preg_match('/^(patient_|encounter_|batch_|report_title)/', $c)));
                        if (!empty($columns)) {
                            $cardRows[] = $this->buildRow($pendingLabel, $columns, $cols, $formLabels);
                        }
                        $pendingLabel = null;
                    }
                }
            }
            // Section blocks outside tables: <div class="section-title">Label</div> ... value.
            if (preg_match_all('/<div class="section-title">(.*?)<\/div>\s*<div class="(?:phase-box|stage-box|report-text)">\s*(.*?)\s*<\/div>/s', $src, $sm, PREG_SET_ORDER)) {
                foreach ($sm as $smm) {
                    $label = trim(html_entity_decode(strip_tags($smm[1])));
                    if (preg_match("/xlt\\('([^']+)'\\)/", $smm[1], $lm)) {
                        $label = $lm[1];
                    }
                    $columns = $this->columnsInExpr($smm[2], $varCol, $src);
                    if (empty($columns)) {
                        continue;
                    }
                    $cardRows[] = $this->buildRow($label, $columns, $cols, $formLabels);
                }
            }

            // CardPrintReport::section() blocks (echo print style): pairs of
            // xlt('Label') followed by $fv('column') / $study['column'].
            if (preg_match_all('/CardPrintReport::section\((.*?)(?:\)\);|\?>)/s', $src, $secM, PREG_SET_ORDER)) {
                foreach ($secM as $sec) {
                    $block = $sec[1];
                    $labels = [];
                    if (preg_match_all("/xlt\\('([^']+)'\\)/", $block, $lm, PREG_OFFSET_CAPTURE)) {
                        foreach ($lm[1] as [$lab, $off]) {
                            $labels[$off] = $lab;
                        }
                    }
                    $colsByOff = [];
                    if (preg_match_all("/\\\$fv\\('([a-z_0-9]+)'\\)|\\\$\w+\\[\\'([a-z_0-9]+)\\'\\]/", $block, $cm, PREG_OFFSET_CAPTURE)) {
                        // offset per match is whole match; use per-column search below instead.
                    }
                    $offs = array_keys($labels);
                    foreach ($labels as $idx => $lab) {
                        $off = $offs[$idx];
                        // Skip the section title (first argument, followed by ", [").
                        $afterLabel = substr($block, (int)$off + strlen($lab) + 2);
                        if (preg_match('/^\s*,\s*\[/', $afterLabel)) {
                            continue;
                        }
                        // Value belongs to this label: search only up to the next label.
                        $nextOff = $offs[$idx + 1] ?? strlen($block);
                        $window = substr($block, (int)$off, (int)$nextOff - (int)$off);
                        if (preg_match("/\$fv\('([a-z_0-9]+)'\)|\$fl\('([a-z_0-9]+)'|\$study\['([a-z_0-9]+)'\]/", $window, $cm)) {
                            $col = $cm[1] !== '' ? $cm[1] : ($cm[2] !== '' ? $cm[2] : ($cm[3] ?? ''));
                            if ($col !== '') {
                                $cardRows[] = $this->buildRow($lab, [$col], $cols, $formLabels);
                            }
                        }
                    }
                }
            }

            $this->flagCardRows($cardRows, $cols);
            foreach ($cardRows as $r) {
                if (in_array('MISMATCH', $r['flags'], true) || in_array('DUPLICATE', $r['flags'], true)) {
                    $fail = true;
                }
            }
            $rows[$card] = $cardRows;
        }

        $out = "# Print label audit\n\n";
        $out .= "Generated by tools/audit_prints.php. One row per label/value pair rendered outside the generic sections.\n\n";
        $notPrintedByCard = [];
        foreach ($rows as $card => $cardRows) {
            $cols = $this->schemaColumns($this->cardTable($card));
            // finalize-required columns for this card (from its save.php).
            $finalize = [];
            $save = $this->root . '/cards/' . $card . '/save.php';
            if (is_file($save)) {
                $saveSrc = file_get_contents($save);
                if (preg_match_all('/if\s*\(\s*(?:\$status\s*===\s*\'final\'|\$action\s*===\s*\'save_final\')[^\{]*\{(.*?)\n\s*\}/s', $saveSrc, $fm)) {
                    foreach ($fm[1] as $block) {
                        if (preg_match_all('/\$p\s*\[\s*\'([a-z_0-9]+)\'\s*\]/', $block, $pm)) {
                            foreach (array_unique($pm[1]) as $f) {
                                $finalize[$f] = true;
                            }
                        }
                    }
                }
            }
            $printed = [];
            foreach ($cardRows as $r) {
                foreach ($r['columns'] as $c) {
                    $printed[$c] = true;
                }
            }
            $notPrinted = [];
            // Generic sections print every non-skip column; skip-list columns
            // are printed only if the header shows them (pid/encounter/status/user).
            $headerShown = ['pid' => true, 'encounter' => true, 'status' => true, 'user' => true];
            foreach ($skip as $cname) {
                if (isset($cols[$cname]) && !isset($headerShown[$cname])) {
                    $notPrinted[] = $cname;
                }
            }
            $notPrintedByCard[$card] = $notPrinted;

            $out .= "## $card\n\n";
            $out .= "| Card | Printed label | Column(s) | Column COMMENT | Unit shown | Printed in the PDF header | Flags |\n";
            $out .= "|---|---|---|---|---|---|---|\n";
            foreach ($cardRows as $r) {
                $out .= "| $card | {$r['label']} | " . implode(', ', $r['columns']) . " | {$r['comment']} | {$r['unit']} | yes | " . implode(', ', $r['flags']) . " |\n";
            }
            $out .= "\n";
        }
        $out .= "## Not printed anywhere\n\n";
        foreach ($notPrintedByCard as $card => $notPrinted) {
            $cols = $this->schemaColumns($this->cardTable($card));
            $out .= "### $card\n\n";
            if (empty($notPrinted)) {
                $out .= "None.\n\n";
                continue;
            }
            $out .= "| Column | COMMENT | Required to finalize | Calculated |\n";
            $out .= "|---|---|---|---|\n";
            foreach ($notPrinted as $cname) {
                $c = $cols[$cname] ?? null;
                $calc = $c !== null && str_contains(strtolower($c['comment'] ?? ''), 'calculated') ? 'yes' : 'no';
                $req = !empty($finalize[$cname]) ? 'yes' : 'no';
                $out .= "| $cname | " . ($c['comment'] ?? '') . " | $req | $calc |\n";
            }
            $out .= "\n";
        }
        file_put_contents($this->root . '/docs/print_label_audit.md', $out);
        echo $out;
        return $fail ? 1 : 0;
    }

    private function columnsInExpr(string $expr, array $varCol, string $fullSrc): array
    {
        $columns = [];
        // $var usages where $var maps to a column.
        if (preg_match_all('/\$(\w+)\b/', $expr, $m)) {
            foreach ($m[1] as $v) {
                if (isset($varCol[$v])) {
                    foreach ($varCol[$v] as $c) {
                        $columns[] = $c;
                    }
                }
            }
        }
        // Direct $study['col'] in the expression.
        if (preg_match_all('/\$study\[\'([a-z_0-9]+)\'\]/', $expr, $m)) {
            foreach ($m[1] as $c) {
                $columns[] = $c;
            }
        }
        // CardPrintReport::formatValue($var, 'list') etc. already covered by $var.
        return array_values(array_unique($columns));
    }

    private function buildRow(string $label, array $columns, array $cols, array $formLabels): array
    {
        $comments = [];
        $unit = '';
        foreach ($columns as $c) {
            $comments[] = $cols[$c]['comment'] ?? '(not a column)';
            if ($unit === '') {
                $unit = $this->unitInLabel($label);
            }
        }
        return [
            'label' => $label,
            'columns' => $columns,
            'comment' => implode(' / ', $comments),
            'unit' => $unit,
            'flags' => [],
            'formLabels' => $formLabels,
        ];
    }

    private function unitInLabel(string $label): string
    {
        // Unit = text in the LAST parentheses: "BMI (kg/m²)", "Rate (bpm)".
        if (preg_match_all('/\(([^()]+)\)/', $label, $m)) {
            $last = trim(end($m[1]));
            // Any single-token parenthesized text counts as a unit claim.
            if ($last !== '' && !str_contains($last, ' ')) {
                return $last;
            }
        }
        // Standalone unit words: "METs Achieved", "Duration in seconds".
        if (preg_match('/\b(METs|mmHg|bpm|lpm|pg\/mL|mg\/week)\b/i', $label, $m)) {
            return $m[1];
        }
        return '';
    }

    /** Unit mentioned in a column COMMENT, normalized (² -> 2, lowercase). */
    private function unitInComment(string $comment): string
    {
        $phrases = [
            'beats per minute' => 'bpm',
            'millimeters of mercury' => 'mmhg',
            'millimetres of mercury' => 'mmhg',
            'centimeters' => 'cm',
            'milliliters' => 'ml',
            'milliseconds' => 'ms',
            'kilograms' => 'kg',
            'seconds' => 's',
        ];
        $lower = strtolower(str_replace('²', '2', $comment));
        foreach ($phrases as $phrase => $unit) {
            if (str_contains($lower, $phrase)) {
                return $unit;
            }
        }
        $units = ['pg/ml', 'mg/week', 'kg/m2', 'kg/m²', 'METs', 'mmHg', 'bpm', 'lpm', 'm/s', 'cm/s', 'mm', 'cm', 'kg', 'mL', 'ms', 'hours', 'hour', 'mg', 'g', 'm2', 'm²', '%', 's', 'h'];
        $lower = strtolower(str_replace('²', '2', $comment));
        foreach ($units as $u) {
            $u = strtolower(str_replace('²', '2', $u));
            if (preg_match('/\b' . preg_quote($u, '/') . '\b/', $lower)) {
                return $u;
            }
        }
        return '';
    }

    /** @return string[] card codes whose card_type is not 'tool' */
    private function studyCards(): array
    {
        $parsed = SchemaChecker::parseSqlFile($this->root . '/table.sql');
        $tool = [];
        foreach ($parsed['seeds'] as $s) {
            if (($s['table'] ?? '') !== 'card_modules') {
                continue;
            }
            $cols = $s['columns'] ?? [];
            $ci = array_search('code', $cols, true);
            $ti = array_search('card_type', $cols, true);
            foreach (($s['rows'] ?? []) as $r) {
                $p = explode('|', $r);
                if ($ti !== false && ($p[$ti] ?? 'study') === 'tool') {
                    $tool[$p[$ci]] = true;
                }
            }
        }
        $cards = [];
        foreach (glob($this->root . '/cards/*/print.php') ?: [] as $abs) {
            $card = basename(dirname($abs));
            if (!isset($tool[$card])) {
                $cards[] = $card;
            }
        }
        sort($cards);
        return $cards;
    }

    /** Skip list used by CardPrintReport::genericSections(), parsed from lib. */
    private function genericSkip(): array
    {
        $src = file_get_contents($this->root . '/lib/CardPrintReport.php');
        if ($src === false || !preg_match('/\$skip = array_merge\(\s*\[(.*?)\]/s', $src, $m)) {
            return ['id', 'pid', 'encounter', 'cardiology_id', 'activity', 'authorized', 'groupname', 'user', 'status'];
        }
        preg_match_all("/'([a-z_0-9]+)'/", $m[1], $km);
        return $km[1];
    }

    private function flagCardRows(array &$cardRows, array $cols): void
    {
        $byColumn = [];
        foreach ($cardRows as $i => $r) {
            foreach ($r['columns'] as $c) {
                $byColumn[$c][] = $i;
            }
        }
        foreach ($cardRows as $i => $r) {
            $flags = [];
            // MISMATCH: label vs COMMENT meaningful words. Suppressed when the
            // label IS the form's xl() string for that column (authoritative),
            // and skipped for single-word labels (too weak a signal).
            $labelWords = $this->words($r['label']);
            $commentWords = $this->words($r['comment']);
            $matchesForm = true;
            foreach ($r['columns'] as $c) {
                $form = $r['formLabels'][$c] ?? null;
                if ($form !== null && $form !== $r['label']) {
                    $matchesForm = false;
                }
            }
            if (count($labelWords) > 1 && !empty($commentWords) && !$matchesForm) {
                $shared = array_intersect($labelWords, $commentWords);
                if (empty($shared)) {
                    $flags[] = 'MISMATCH';
                }
            }
            // UNIT-MISMATCH / NO-UNIT: label unit vs COMMENT unit.
            foreach ($r['columns'] as $c) {
                $col = $cols[$c] ?? null;
                if ($col === null) {
                    continue;
                }
                $labelUnit = $this->unitInLabel($r['label']);
                $commentUnit = $this->unitInComment($col['comment'] ?? '');
                $commentUnitNorm = strtolower(str_replace('²', '2', $commentUnit));
                $labelUnitNorm = strtolower(str_replace('²', '2', $labelUnit));
                if ($labelUnit !== '' && $commentUnit !== '' && $labelUnitNorm !== $commentUnitNorm) {
                    $flags[] = 'UNIT-MISMATCH';
                }
                $t = strtolower($col['type']);
                if (preg_match('/^(decimal|int|smallint|tinyint|bigint|float|double)/', $t)
                    && $commentUnit !== '' && $labelUnit === '') {
                    $flags[] = 'NO-UNIT';
                }
            }
            // DUPLICATE: same column with a different label elsewhere.
            foreach ($r['columns'] as $c) {
                if (count($byColumn[$c] ?? []) > 1) {
                    $labels = array_unique(array_map(static fn($j) => $cardRows[$j]['label'], $byColumn[$c]));
                    if (count($labels) > 1) {
                        $flags[] = 'DUPLICATE';
                    }
                }
            }
            // PARTIAL: related groups (flag only when not all are printed).
            $allPrinted = [];
            foreach ($cardRows as $rr) {
                foreach ($rr['columns'] as $c) {
                    $allPrinted[$c] = true;
                }
            }
            foreach (self::GROUPS as $group) {
                $present = array_intersect($group, array_keys($allPrinted));
                if (!empty($present) && count($present) < count($group)
                    && !empty(array_intersect($group, $r['columns']))) {
                    $flags[] = 'PARTIAL';
                }
            }
            // LABEL-NOT-IN-FORM.
            foreach ($r['columns'] as $c) {
                $form = $r['formLabels'][$c] ?? null;
                if ($form !== null && $form !== $r['label']) {
                    $flags[] = 'LABEL-NOT-IN-FORM';
                }
            }
            $cardRows[$i]['flags'] = array_values(array_unique($flags));
        }
    }
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === realpath(__FILE__)) {
    $root = dirname(__DIR__);
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--root=')) {
            $root = substr($arg, 7);
        }
    }
    exit((new PrintLabelAudit($root))->run());
}
