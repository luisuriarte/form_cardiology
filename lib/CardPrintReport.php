<?php

/**
 * CardPrintReport - Shared rendering helpers for cardiology printable reports
 *
 * Patient and encounter data are loaded through OpenEMR's own services
 * (PatientService::findByPid() and EncounterService::getOneByPidEid()),
 * which exist with the same signatures in OpenEMR 8.2.0 and 8.5.0.
 * If the services are unavailable or fail, the helper falls back to the
 * direct patient_data / form_encounter queries.
 *
 * Compatible with OpenEMR 8.2.0 - 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Forms\Cardiology;

use OpenEMR\Services\EncounterService;
use OpenEMR\Services\PatientService;
use OpenEMR\Services\UserService;

final class CardPrintReport
{
    private static bool $styleEmitted = false;

    private static function fieldStyle(): string
    {
        if (self::$styleEmitted) {
            return '';
        }
        self::$styleEmitted = true;

        return '<style>'
            . '.fields-table{width:100%;border-collapse:collapse;margin-bottom:8px;}'
            . '.fields-table td{padding:4px 8px;font-size:12px;border-bottom:1px solid #e2e8f0;vertical-align:top;}'
            . '.fields-table tr:nth-child(even){background-color:#f8fafc;}'
            . '.fields-table .fl{color:#475569;font-weight:bold;width:25%;}'
            . '.fields-table .fv{color:#0f172a;width:25%;}'
            . '.fields-wide .fl{width:25%;} .fields-wide .fv{width:75%;}'
            . '.fields-table tr{page-break-inside:avoid;}'
            . '.report-text{font-size:12px;color:#334155;}'
            . '</style>';
    }

    /**
     * Returns a user as "Lastname, Firstname" via UserService
     * (getUser() / getUserByUsername(), same in 8.2.0 and 8.5.0),
     * falling back to the raw username when the user is not found.
     */
    public static function userDisplayName(?string $username = null, mixed $userId = null): string
    {
        $row = null;

        try {
            if (class_exists(UserService::class)) {
                $service = new UserService();
                if ($userId !== null && (int)$userId > 0) {
                    $row = $service->getUser((int)$userId);
                } elseif ($username !== null && trim($username) !== '') {
                    $row = $service->getUserByUsername(trim($username));
                }
            }
        } catch (\Throwable $e) {
            $row = null;
        }

        if (!is_array($row) || empty($row)) {
            if ($userId !== null && (int)$userId > 0) {
                $row = sqlQuery('SELECT * FROM `users` WHERE id = ? LIMIT 1', [(int)$userId]);
            } elseif ($username !== null && trim($username) !== '') {
                $row = sqlQuery('SELECT * FROM `users` WHERE BINARY `username` = ? LIMIT 1', [trim($username)]);
            }
        }

        if (is_array($row) && !empty($row)) {
            $name = trim(
                (string)($row['lname'] ?? '') . ', '
                . (string)($row['fname'] ?? '') . ' '
                . (string)($row['mname'] ?? '')
            );
            $name = trim($name, ', ');
            if ($name !== '') {
                return $name;
            }
        }

        return trim((string)($username ?? ''));
    }

    /**
     * Screen-only toolbar with Print (browser dialog) and Download PDF
     * (server-side Dompdf via ?download=1). Hidden when printing.
     */
    public static function toolbarHtml(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $path = strtok($uri, '?');
        $query = $_GET;
        $query['download'] = '1';
        $downloadUrl = $path . '?' . http_build_query($query);

        return '<div class="no-print" style="margin-bottom:12px;padding:10px 12px;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:6px;">'
            . '<button type="button" onclick="window.print()" style="padding:7px 16px;margin-right:8px;background:#0284c7;color:#fff;border:1px solid #0284c7;border-radius:4px;cursor:pointer;font-size:13px;">'
            . text(xlt('Print')) . '</button>'
            . '<a href="' . attr($downloadUrl) . '" style="padding:7px 16px;background:#fff;color:#0369a1;border:1px solid #0284c7;border-radius:4px;text-decoration:none;font-size:13px;">'
            . text(xlt('Download PDF')) . '</a>'
            . '</div>'
            . '<style>@media print{.no-print{display:none !important;}}</style>';
    }

    /**
     * Renders the buffered report HTML to a real PDF file with Dompdf
     * (bundled with OpenEMR 8.2.0/8.5.0) and streams it as a download.
     */
    public static function downloadPdf(string $html, string $filename): never
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo xlt('PDF generation is not available on this server.');
            exit;
        }

        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            'defaultFont' => 'DejaVu Sans',
        ]);
        // Dompdf requires the GD extension for <img> tags (including the logo).
        // Without GD, strip raster images so the PDF still renders; SVG charts
        // (embedded as data-URI <img>) and tables are unaffected. Attached
        // images are then listed by name.
        if (!extension_loaded('gd')) {
            $html = (string)preg_replace('/<img\b[^>]*src="data:image\/(?:png|jpe?g|gif|bmp|webp)[^"]*"[^>]*>/i', '', $html);
        }
        // Never include the screen-only Print / Download toolbar in the PDF.
        $html = (string)preg_replace(
            '/<div class="no-print"[^>]*>.*?<\/div>\s*<style>@media print\{.*?\}\}<\/style>/s',
            '',
            $html
        );
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream($filename, ['Attachment' => true]);
        exit;
    }

    /**
     * Evolution charts on their own page(s) after the report body.
     *
     * The browser charts use Chart.js <canvas>, which Dompdf cannot execute
     * (and the server may not have GD for raster images). Instead the same
     * series data from CardCharts is drawn server-side as inline SVG, which
     * php-svg-lib renders without GD.
     */
    public static function chartsHtml(string $cardCode, int $studyId, int $pid): string
    {
        if ($cardCode === '' || $studyId <= 0 || $pid <= 0) {
            return '';
        }

        require_once __DIR__ . '/CardCharts.php';

        $charts = array_merge(
            CardCharts::getStudySeries($cardCode, $studyId, $pid),
            CardCharts::getLongitudinal($cardCode, $pid, $studyId)
        );
        $charts = array_values(array_filter(
            $charts,
            static fn($c): bool => !empty($c['has_data'])
        ));
        if (empty($charts)) {
            return '';
        }

        $html = '<div style="page-break-before: always;">'
            . '<div class="section-title">' . text(xlt('Evolution')) . '</div>';

        foreach ($charts as $chart) {
            $title = (string)($chart['title'] ?? '');
            $unit = (string)($chart['unit'] ?? '');
            $html .= '<div style="page-break-inside: avoid; margin-bottom: 14px;">'
                . '<div style="text-align: center; font-size: 12px; font-weight: bold; color: #475569; margin-bottom: 4px;">'
                . text($title)
                . ($unit !== '' ? ' (' . text($unit) . ')' : '')
                . '</div>';

            // Legend in HTML (SVG text rendering is unreliable in php-svg-lib).
            $legend = '';
            foreach ($chart['datasets'] ?? [] as $i => $ds) {
                $color = self::chartColor($i);
                $legend .= '<span style="color: ' . $color . '; font-size: 11px; margin-right: 10px;">&#9632; '
                    . text((string)($ds['label'] ?? '')) . '</span>';
            }
            if ($legend !== '') {
                $html .= '<div style="text-align: center; margin-bottom: 4px;">' . $legend . '</div>';
            }

            $html .= '<div style="text-align: center;"><img src="data:image/svg+xml;base64,'
                . base64_encode(self::svgLineChart($chart))
                . '" width="520" height="180" alt="' . attr($title) . '"></div></div>';
        }

        return $html . '</div>';
    }

    /**
     * Attached documents: non-image files are listed in a table; image files
     * are embedded one per page when GD is available (Dompdf needs GD <img>).
     */
    public static function attachedDocumentsHtml(string $cardCode, int $studyId, int $pid): string
    {
        if ($cardCode === '' || $studyId <= 0 || $pid <= 0) {
            return '';
        }

        require_once __DIR__ . '/CardDocuments.php';

        $attachments = CardDocuments::listForStudy($cardCode, $studyId, $pid);
        if (empty($attachments)) {
            return '';
        }

        $images = [];
        $others = [];
        foreach ($attachments as $att) {
            $mime = strtolower((string)($att['mimetype'] ?? ''));
            if (str_starts_with($mime, 'image/')) {
                $images[] = $att;
            } else {
                $others[] = $att;
            }
        }

        $html = '<div style="page-break-before: always;">'
            . '<div class="section-title">' . text(xlt('Attached Documents')) . '</div>';

        if (!empty($others)) {
            $html .= '<table class="fields-table">';
            foreach ($others as $att) {
                $html .= '<tr><td class="fl">' . text(xlt('Name')) . '</td><td class="fv">'
                    . text((string)($att['doc_name'] ?? '')) . '</td>'
                    . '<td class="fl">' . text(xlt('Date')) . '</td><td class="fv">'
                    . text(self::formatValue($att['doc_date'] ?? $att['linked_at'] ?? null)) . '</td></tr>'
                    . '<tr><td class="fl">' . text(xlt('Category')) . '</td><td class="fv">'
                    . text((string)($att['category_name'] ?? '')) . '</td>'
                    . '<td class="fl">' . text(xlt('Size')) . '</td><td class="fv">'
                    . text(CardDocuments::formatBytes((int)($att['size'] ?? 0))) . '</td></tr>';
            }
            $html .= '</table>';
        }

        $canEmbed = extension_loaded('gd');
        if (!empty($images)) {
            if (!$canEmbed) {
                $html .= '<div class="report-text">'
                    . text(xlt('Image preview requires the GD extension on the server.'))
                    . '</div>';
            }
            foreach (array_slice($images, 0, 12) as $index => $att) {
                $name = (string)($att['doc_name'] ?? '');
                $caption = $name . ' — ' . (string)($att['category_name'] ?? '') . ' — '
                    . self::formatValue($att['doc_date'] ?? $att['linked_at'] ?? null);

                if (!$canEmbed) {
                    $html .= '<div class="report-text">' . text($caption) . '</div>';
                    continue;
                }

                $dataUri = self::imageDataUri((int)($att['document_id'] ?? 0), $pid, $name, (string)($att['mimetype'] ?? ''));
                if ($dataUri === '') {
                    $html .= '<div class="report-text">' . text($caption) . '</div>';
                    continue;
                }

                // First image goes right below the title (same page); each
                // following image gets its own page.
                $pageBreak = $index > 0 ? 'page-break-before: always; ' : '';
                $html .= '<div style="' . $pageBreak . 'text-align: center;">'
                    . '<div style="font-size: 12px; font-weight: bold; color: #475569; margin-bottom: 6px;">'
                    . text($caption) . '</div>'
                    . '<img src="' . attr($dataUri) . '" style="max-width: 170mm; max-height: 230mm;">'
                    . '</div>';
            }
        }

        return $html . '</div>';
    }

    private static function chartColor(int $index): string
    {
        $colors = ['#0284c7', '#dc2626', '#16a34a', '#9333ea', '#ea580c', '#0891b2'];
        return $colors[$index % count($colors)];
    }

    /**
     * @param array<string, mixed> $chart CardCharts series structure
     */
    private static function svgLineChart(array $chart): string
    {
        $width = 520;
        $height = 180;
        $padL = 32;
        $padR = 10;
        $padT = 10;
        $padB = 22;
        $plotW = $width - $padL - $padR;
        $plotH = $height - $padT - $padB;

        $values = [];
        foreach ($chart['datasets'] ?? [] as $ds) {
            foreach ($ds['data'] ?? [] as $v) {
                if ($v !== null && is_numeric($v)) {
                    $values[] = (float)$v;
                }
            }
        }
        if (empty($values)) {
            return '';
        }

        $min = isset($chart['y_min']) ? (float)$chart['y_min'] : min($values);
        $max = isset($chart['y_max']) ? (float)$chart['y_max'] : max($values);
        if ($max <= $min) {
            $max = $min + 1;
        }
        $range = $max - $min;
        $min -= $range * 0.1;
        $max += $range * 0.1;

        $count = max(1, count($chart['labels'] ?? []));
        $x = static fn(int $i): float => $padL + ($count <= 1 ? $plotW / 2 : ($i / ($count - 1)) * $plotW);
        $y = static fn(float $v): float => $padT + $plotH - ((($v - $min) / ($max - $min)) * $plotH);

        $svg = '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '" xmlns="http://www.w3.org/2000/svg">';
        $svg .= '<line x1="' . $padL . '" y1="' . ($padT + $plotH) . '" x2="' . ($padL + $plotW) . '" y2="' . ($padT + $plotH) . '" stroke="#cbd5e1" stroke-width="1"/>';
        $svg .= '<line x1="' . $padL . '" y1="' . $padT . '" x2="' . $padL . '" y2="' . ($padT + $plotH) . '" stroke="#cbd5e1" stroke-width="1"/>';

        foreach ($chart['datasets'] ?? [] as $di => $ds) {
            $color = self::chartColor($di);
            $segment = [];
            foreach ($ds['data'] ?? [] as $i => $v) {
                if ($v === null || !is_numeric($v)) {
                    if (count($segment) > 1) {
                        $svg .= '<polyline points="' . implode(' ', $segment) . '" fill="none" stroke="' . $color . '" stroke-width="2"/>';
                    }
                    $segment = [];
                    continue;
                }
                $px = round($x($i), 1);
                $py = round($y((float)$v), 1);
                $segment[] = $px . ',' . $py;
                $svg .= '<circle cx="' . $px . '" cy="' . $py . '" r="2.5" fill="' . $color . '"/>';
            }
            if (count($segment) > 1) {
                $svg .= '<polyline points="' . implode(' ', $segment) . '" fill="none" stroke="' . $color . '" stroke-width="2"/>';
            }
        }

        return $svg . '</svg>';
    }

    private static function imageDataUri(int $documentId, int $pid, string $name, string $storedMime): string
    {
        if ($documentId <= 0) {
            return '';
        }

        require_once CardDocuments::getDocumentClassPath();

        try {
            $doc = new \Document($documentId);
            if (!$doc->can_access()) {
                return '';
            }
            $content = $doc->get_data();
        } catch (\Throwable $e) {
            return '';
        }

        if (!is_string($content) || $content === '' || strlen($content) > 8388608) {
            return '';
        }

        $view = CardDocuments::resolveViewMime($storedMime, $name);
        $mime = $view['mime'];
        if (!str_starts_with($mime, 'image/')) {
            return '';
        }

        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }

    /**
     * @return array<string, mixed>
     */
    public static function patient(int $pid): array
    {
        if ($pid <= 0) {
            return [];
        }

        try {
            if (class_exists(PatientService::class)) {
                $row = (new PatientService())->findByPid($pid);
                if (is_array($row) && !empty($row)) {
                    // findByPid() returns a single patient_data row (array).
                    if (isset($row[0]) && is_array($row[0])) {
                        return $row[0];
                    }
                    return $row;
                }
            }
        } catch (\Throwable $e) {
            // Fall through to the direct query below.
        }

        $row = sqlQuery('SELECT * FROM `patient_data` WHERE pid = ? LIMIT 1', [$pid]);

        return is_array($row) ? $row : [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function encounter(int $pid, int $encounterId): array
    {
        if ($pid <= 0 || $encounterId <= 0) {
            return [];
        }

        try {
            if (class_exists(EncounterService::class)) {
                $row = (new EncounterService())->getOneByPidEid($pid, $encounterId);
                if (is_array($row) && !empty($row)) {
                    return $row;
                }
            }
        } catch (\Throwable $e) {
            // Fall through to the direct query below.
        }

        $row = sqlQuery(
            'SELECT * FROM `form_encounter` WHERE pid = ? AND encounter = ? LIMIT 1',
            [$pid, $encounterId]
        );

        return is_array($row) ? $row : [];
    }

    public static function patientName(array $patient): string
    {
        $name = trim(
            (string)($patient['lname'] ?? '') . ', '
            . (string)($patient['fname'] ?? '') . ' '
            . (string)($patient['mname'] ?? '')
        );

        return trim($name, ', ');
    }

    public static function patientAge(array $patient): string
    {
        $dob = (string)($patient['DOB'] ?? '');
        if ($dob === '') {
            return '';
        }

        $dobYmd = str_replace('-', '', $dob);

        try {
            if (class_exists(PatientService::class)) {
                $display = (new PatientService())->getPatientAgeDisplay($dobYmd);
                if (is_string($display) && $display !== '') {
                    return $display;
                }
            }
        } catch (\Throwable $e) {
            // Fall through to the manual calculation.
        }

        $dobTime = strtotime($dob);
        if ($dobTime === false) {
            return '';
        }

        $years = (int)floor((time() - $dobTime) / (365.25 * 24 * 3600));

        return $years >= 0 ? (string)$years : '';
    }

    public static function formatValue(mixed $value, ?string $listId = null): string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return '—';
        }

        $text = trim((string)$value);

        if ($listId !== null && $listId !== '') {
            $title = self::listTitle($listId, $text);
            if ($title !== '') {
                return $title;
            }
        }

        // Date / datetime values: 2025-11-10 or 2025-11-10 10:00:00,
        // shown in the user's configured format via DateFormatterUtils.
        if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $text)) {
            require_once __DIR__ . '/CardDate.php';
            $formatted = strlen($text) > 10
                ? CardDate::displayDateTime($text)
                : CardDate::displayDate($text);
            if ($formatted !== '') {
                return $formatted;
            }
        }

        return $text;
    }

    public static function listTitle(string $listId, string $optionId): string
    {
        $row = sqlQuery(
            'SELECT title FROM `list_options` WHERE list_id = ? AND option_id = ? LIMIT 1',
            [$listId, $optionId]
        );

        return is_array($row) ? (string)($row['title'] ?? '') : '';
    }

    /**
     * Humanizes a database column name into a print label, keeping medical
     * acronyms and units readable: lvedd_mm -> "LVEDD (mm)".
     */
    public static function humanLabel(string $column): string
    {
        $units = [
            'mm' => 'mm', 'cm' => 'cm', 'm' => 'm', 'kg' => 'kg', 'g' => 'g',
            'ml' => 'mL', 'l' => 'L', 'bpm' => 'bpm', 'mmhg' => 'mmHg',
            'ms' => 'ms', 'pct' => '%', 'm2' => 'm²', 'cm2' => 'cm²',
            'm_s' => 'm/s', 'cm_s' => 'cm/s', 'g_m2' => 'g/m²', 'ml_m2' => 'mL/m²',
        ];
        $acronyms = [
            'lvef' => 'LVEF', 'lvedd' => 'LVEDD', 'lvesd' => 'LVESD',
            'lvedv' => 'LVEDV', 'lvesv' => 'LVESV', 'ivs' => 'IVS',
            'pw' => 'PW', 'la' => 'LA', 'ra' => 'RA', 'rv' => 'RV',
            'ivc' => 'IVC', 'rap' => 'RAP', 'tr' => 'TR', 'pasp' => 'PASP',
            'ao' => 'Ao', 'mv' => 'MV', 'tv' => 'TV', 'pv' => 'PV',
            'ecg' => 'ECG', 'bp' => 'BP', 'bsa' => 'BSA', 'qt' => 'QT',
            'qrs' => 'QRS', 'tapse' => 'TAPSE', 'nyha' => 'NYHA',
        ];

        $parts = explode('_', $column);
        $unit = '';
        if (count($parts) > 2) {
            $two = strtolower($parts[count($parts) - 2] . '_' . $parts[count($parts) - 1]);
            if (isset($units[$two])) {
                $unit = ' (' . $units[$two] . ')';
                array_pop($parts);
                array_pop($parts);
            }
        }
        if ($unit === '') {
            $last = strtolower((string)end($parts));
            if (isset($units[$last]) && count($parts) > 1) {
                $unit = ' (' . $units[$last] . ')';
                array_pop($parts);
            }
        }

        $words = [];
        foreach ($parts as $part) {
            $lower = strtolower($part);
            $words[] = $acronyms[$lower] ?? ucfirst($lower);
        }

        return implode(' ', $words) . $unit;
    }

    /**
     * @param array<string, mixed> $study
     * @param list<string>         $exclude
     * @param array<string, string> $listMap field => list_options list_id
     */
    public static function genericSections(array $study, string $title, array $exclude = [], array $listMap = []): string
    {
        $skip = array_merge(
            [
                'id', 'pid', 'encounter', 'cardiology_id', 'activity', 'authorized', 'groupname', 'user', 'status',
                // Injected by print.php from PatientService / EncounterService; already in the header.
                'patient_name', 'patient_dob', 'patient_sex', 'patient_dni', 'patient_age',
                'encounter_label', 'batch_date_formatted',
            ],
            $exclude
        );
        $rows = [];
        foreach ($study as $column => $value) {
            if (!is_string($column) || in_array($column, $skip, true)) {
                continue;
            }
            $rows[] = [self::humanLabel($column), self::formatValue($value, $listMap[$column] ?? null)];
        }

        return self::section($title, $rows, 2);
    }

    /**
     * @param list<array{0: string, 1: string}> $rows [label, formatted value]
     */
    public static function section(string $title, array $rows, int $pairsPerRow = 2, bool $wide = false): string
    {
        if (empty($rows)) {
            return '';
        }

        $html = self::fieldStyle()
            . '<div class="section-title">' . text(xlt($title)) . '</div>';

        if ($wide) {
            $html .= '<table class="fields-table fields-wide">';
            foreach ($rows as [$label, $value]) {
                $html .= '<tr><td class="fl">' . text($label) . '</td><td class="fv">'
                    . text($value) . '</td></tr>';
            }
            $html .= '</table>';
            return $html;
        }

        $pairsPerRow = max(1, $pairsPerRow);
        $html .= '<table class="fields-table">';
        foreach (array_chunk($rows, $pairsPerRow) as $chunk) {
            $html .= '<tr>';
            foreach ($chunk as [$label, $value]) {
                $html .= '<td class="fl">' . text($label) . '</td><td class="fv">' . text($value) . '</td>';
            }
            // Pad incomplete rows so the table stays aligned.
            for ($i = count($chunk); $i < $pairsPerRow; $i++) {
                $html .= '<td class="fl">&nbsp;</td><td class="fv">&nbsp;</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        return $html;
    }

    /**
     * Patient + encounter header table, fed by the OpenEMR services.
     *
     * @param array<string, mixed> $study
     * @param array<string, mixed> $patient
     * @param array<string, mixed> $encounter
     */
    public static function patientEncounterTable(
        array $study,
        array $patient,
        array $encounter,
        int $pid,
        int $encounterId,
        int $studyId
    ): string {
        $status = (string)($study['status'] ?? '');
        $statusText = $status === 'final' ? xlt('Final') : xlt('Draft');

        $encDate = self::formatValue($encounter['date'] ?? null);
        $encReason = self::formatValue($encounter['reason'] ?? null);
        $provider = self::userDisplayName(
            (string)($encounter['provider_username'] ?? ''),
            $encounter['provider_id'] ?? null
        );
        if ($provider === '') {
            $provider = self::userDisplayName((string)($study['user'] ?? ''));
        }
        if ($provider === '') {
            $provider = '—';
        }
        $facility = self::formatValue($encounter['facility_name'] ?? null);
        $age = self::patientAge($patient);
        $userName = self::userDisplayName((string)($study['user'] ?? ''));
        if ($userName === '') {
            $userName = '—';
        }

        $rows = [
            [xlt('Patient'), self::patientName($patient) !== '' ? self::patientName($patient) : '—'],
            [xlt('Medical Record / PID'), '#' . $pid],
            [xlt('ID / Document'), self::formatValue($patient['ss'] ?? $patient['pubpid'] ?? null)],
            [xlt('Date of Birth'), self::formatValue($patient['DOB'] ?? null)],
            [xlt('Age') . ' / ' . xlt('Sex'), ($age !== '' ? $age : '—') . ' / ' . self::formatValue($patient['sex'] ?? null)],
            [xlt('Phone'), self::formatValue($patient['phone_home'] ?? $patient['phone_cell'] ?? null)],
            [xlt('Encounter'), $encounterId > 0 ? (string)$encounterId : '—'],
            [xlt('Encounter Date'), $encDate],
            [xlt('Reason'), $encReason],
            [xlt('Provider'), $provider],
            [xlt('Facility'), $facility],
            [xlt('Study'), '#' . $studyId],
            [xlt('Status'), $statusText],
            [xlt('User'), $userName],
        ];

        $html = '<table class="patient-box">';
        foreach (array_chunk($rows, 2) as $chunk) {
            $html .= '<tr>';
            foreach ($chunk as [$label, $value]) {
                $html .= '<td class="label">' . text($label) . ':</td><td class="val">'
                    . text((string)$value) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        return $html;
    }

    /**
     * Shared print CSS (Dompdf-safe: tables only, no flexbox).
     * A4 portrait with generous margins on every side.
     */
    public static function style(): string
    {
        return <<<'CSS'
        @page {
            margin: 20mm 15mm 18mm 15mm;
            size: A4 portrait;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #1e293b;
            line-height: 1.5;
            margin: 0;
            padding: 4mm;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .logo { text-align: center; margin-bottom: 9px; }
        .logo img { max-height: 90px; max-width: 320px; height: auto; display: inline-block; }
        .header {
            display: table; width: 100%; table-layout: fixed;
            border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 14px;
        }
        .header-left, .header-right { display: table-cell; vertical-align: middle; }
        .header-left { width: 62%; }
        .header-right { width: 38%; text-align: right; color: #64748b; font-size: 11px; }
        .clinic-name { font-size: 20px; font-weight: bold; color: #0f172a; text-transform: uppercase; margin: 0; }
        .clinic-sub { font-size: 10.5px; color: #64748b; margin: 2px 0 0 0; }
        .report-title {
            text-align: center; background-color: #f0f9ff; border: 1px solid #bae6fd;
            color: #0369a1; font-size: 15px; font-weight: bold; letter-spacing: 0.5px;
            padding: 7px 10px; margin: 12px 0 14px 0; border-radius: 4px; text-transform: uppercase;
        }
        .report-meta { text-align: center; font-size: 11px; color: #64748b; margin: -8px 0 12px 0; }
        .patient-box { width: 100%; border: 1px solid #cbd5e1; background-color: #f8fafc;
            border-radius: 4px; margin-bottom: 12px; border-collapse: collapse; }
        .patient-box td { padding: 5px 8px; font-size: 12px; vertical-align: top; }
        .patient-box td.label { font-weight: bold; color: #475569; width: 16%; }
        .patient-box td.val { color: #0f172a; width: 34%; }
        .section-title {
            page-break-after: avoid; font-size: 13px; font-weight: bold; color: #0369a1;
            border-bottom: 1px solid #e2e8f0; padding-bottom: 3px;
            margin-top: 14px; margin-bottom: 6px; text-transform: uppercase;
        }
        .fields-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .fields-table td { padding: 4px 8px; font-size: 12px; border-bottom: 1px solid #e2e8f0;
            border-left: 1px solid #f1f5f9; border-right: 1px solid #f1f5f9; vertical-align: top; }
        .fields-table tr:nth-child(even) { background-color: #f8fafc; }
        .fields-table .fl { color: #475569; font-weight: bold; width: 25%; }
        .fields-table .fv { color: #0f172a; width: 25%; }
        .fields-wide .fl { width: 25%; }
        .fields-wide .fv { width: 75%; }
        .fields-table tr { page-break-inside: avoid; }
        .disclaimer { page-break-inside: avoid; font-size: 9.5px; color: #94a3b8;
            text-align: center; margin-top: 18px; }
CSS;
    }
}
