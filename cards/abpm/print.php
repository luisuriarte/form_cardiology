<?php

/**
 * ABPM card – print.php (modernized)
 *
 * Renders a clean printable report for an Ambulatory Blood Pressure Monitoring study.
 * Opens in a new tab (target="_blank").
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\ABPM
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

require_once('../../../../globals.php');
require_once($GLOBALS['srcdir'] . '/api.inc.php');
require_once(__DIR__ . '/../../lib/CardiologyContainer.php');
require_once(__DIR__ . '/../../lib/CardSettings.php');

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Forms\Cardiology\CardiologyContainer;

// ---------------------------------------------------------------------------
// Logos for PDF generation (Dompdf / php-svg-lib)
// ---------------------------------------------------------------------------
$logo_dir = $GLOBALS['webroot'] . '/assets';
$logoBannerPath = $logo_dir . '/logo_banner.svg';
$logoSqPath     = $logo_dir . '/logo.svg';
$logoBannerPngPath = $logo_dir . '/logo_banner.png';
$logoSqPngPath    = $logo_dir . '/logo.png';

// Resolve the best available logo, following the same pattern as
// patient-express-portal/public/print_pdf.php: prefer CLINIC_LOGO_PATH,
// then explicit candidates, fallback to nothing.
$effectiveLogoPath = defined('CLINIC_LOGO_PATH') && file_exists(CLINIC_LOGO_PATH)
    ? CLINIC_LOGO_PATH
    : (
        $logoBannerPath . file_exists($logoBannerPath) ? $logoBannerPath : null
    ) . '|' . (
        $logoSqPath . file_exists($logoSqPath) ? $logoSqPath : null
    );

$logoBase64 = '';
if ($effectiveLogoPath && str_contains($effectiveLogoPath, '|')) {
    // Fallback path not usable as single file; try each candidate independently
    $tried = [];
    foreach ([$logoBannerPath, $logoSqPath] as $candidate) {
        if ($candidate && file_exists($candidate) && !in_array($candidate, $tried)) {
            $tried[] = $candidate;
            $ext = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
            $mime = $ext === 'svg' ? 'image/svg+xml' : ($ext === 'png' ? 'image/png' : 'image/' . $ext);
            $imgData = file_get_contents($candidate);
            $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode($imgData);
            break;
        }
    }
} elseif ($effectiveLogoPath && file_exists($effectiveLogoPath)) {
    $ext = strtolower(pathinfo($effectiveLogoPath, PATHINFO_EXTENSION));
    $mime = $ext === 'svg' ? 'image/svg+xml' : ($ext === 'png' ? 'image/png' : 'image/' . $ext);
    $imgData = file_get_contents($effectiveLogoPath);
    $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode($imgData);
}

// If no logo found, keep $logoBase64 empty (PDF will have no header image).

if (!AclMain::aclCheckCore('patients', 'med')) {
    die(xlt('Not authorized'));
}

$studyId      = (int)($_GET['id']            ?? 0);
$cardiologyId = (int)($_GET['cardiology_id'] ?? 0);
$pid          = (int)($_GET['pid']           ?? 0);
$encounter    = (int)($_GET['encounter']     ?? 0);

if ($studyId <= 0 || $pid <= 0) {
    die(xlt('Missing required parameters.'));
}

$study = sqlQuery(
    'SELECT * FROM `form_card_abpm` WHERE id = ? AND pid = ? AND activity = 1',
    [$studyId, $pid]
);
if (!$study || !isset($study['id'])) {
    die(xlt('Study not found or has been deleted.'));
}

// ---- ABPM-specific fields ----
$recordingDate = $study['recording_date'] ?? '';
$recordTime    = $study['record_time'] ?? '';
$monitorHours  = (int)($study['monitor_hours'] ?? 24);
$meanSbp       = (float)($study['mean_sbp'] ?? 0);
$meanDbp       = (float)($study['mean_dbp'] ?? 0);
$heartRate     = (int)($study['heart_rate'] ?? 0);
$status        = $study['status'] ?? '';

// ---- Build HTML for Dompdf ----
ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($study['report_title'] ?? 'Informe ABPM') ?> - OpenEMR</title>
    <style>
        @page {
            margin: 15mm 18mm 15mm 18mm;
            size: A4 portrait;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #1e293b;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header {
            display: table;
            width: 100%;
            table-layout: fixed;
        }
        .header-left,
        .header-right {
            display: table-cell;
            vertical-align: middle;
        }
        .header-left {
            width: 60%;
        }
        .header-right {
            width: 40%;
            text-align: right;
            color: #64748b;
            font-size: 8.5px;
        }
        .header-right .sub {
            font-size: 8px;
        }
        .logo {
            text-align: center;
            margin-bottom: 6px;
        }
        .logo img {
            max-height: 52px;
            max-width: 220px;
            height: auto;
            display: inline-block;
        }
        .clinic-name {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            margin: 0;
        }
        .clinic-sub {
            font-size: 8px;
            color: #64748b;
            margin: 2px 0 0 0;
        }
        .report-title {
            text-align: center;
            background-color: #f0f9ff;
            border: 1px solid #bae6fd;
            color: #0369a1;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 0.5px;
            padding: 4px 6px;
            margin: 10px 0 12px 0;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .patient-box {
            width: 100%;
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            border-radius: 4px;
            margin-bottom: 12px;
            border-collapse: collapse;
        }
        .patient-box td {
            padding: 3px 6px;
            font-size: 9px;
            vertical-align: top;
        }
        .patient-box td.label {
            font-weight: bold;
            color: #475569;
            width: 20%;
        }
        .patient-box td.val {
            color: #0f172a;
            width: 40%;
        }
        .section-title {
            font-size: 9.5px;
            font-weight: bold;
            color: #0369a1;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 2px;
            margin-top: 8px;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .report-text {
            font-size: 9px;
            color: #334155;
            line-height: 1.4;
            background-color: #ffffff;
            padding: 5px 7px;
            border: 1px solid #f1f5f9;
            border-radius: 3px;
            margin-bottom: 4px;
        }
        .parameter-row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
            font-size: 9px;
        }
        .parameter-row .label {
            color: #64748b;
        }
        .parameter-row .value {
            color: #0f172a;
            font-weight: 500;
        }
        .disclaimer {
            font-size: 7.5px;
            color: #94a3b8;
            text-align: center;
            margin-top: 14px;
        }
    </style>
</head>
<body>

    <!-- Institutional Logo + Header -->
    <div class="header">
        <div class="header-left">
            <?php if ($logoBase64): ?>
                <div class="logo"><img src="<?= htmlspecialchars($logoBase64) ?>" alt="Logo"></div>
            <?php endif; ?>
            <div class="clinic-name"><?= defined('CLINIC_NAME') ? CLINIC_NAME : '' ?></div>
            <div class="clinic-sub"><?= defined('CLINIC_ADDRESS') ? CLINIC_ADDRESS : '' ?></div>
            <div class="clinic-sub"><?= defined('CLINIC_PHONE') && CLINIC_PHONE ? 'Tel: ' . CLINIC_PHONE : '' ?></div>
            <div class="clinic-sub"><?= defined('CLINIC_EMAIL') && CLINIC_EMAIL ? 'Email: ' . CLINIC_EMAIL : '' ?></div>
            <div class="clinic-sub"><?= defined('CLINIC_WEB') && CLINIC_WEB ? 'Web: ' . CLINIC_WEB : '' ?></div>
        </div>
        <div class="header-right">
            <div class="sub"><?= xlt('BIOCHEMISTRY & DIAGNOSTIC SERVICE') ?></div>
            <div class="sub"><?= xlt('Unified Electronic Protocol') ?></div>
            <div class="sub" style="margin-top: 2px;"><?= xlt('Issue Date') ?>: <?= date('d/m/Y H:i:s') ?></div>
        </div>
    </div>

    <!-- Report Title -->
    <div class="report-title">
        <?= htmlspecialchars(xlt('AMBULATORY BLOOD PRESSURE MONITORING REPORT')) ?>
    </div>

    <!-- Patient Card -->
    <table class="patient-box">
        <tr>
            <td class="label"><?= xlt('Patient') ?>:</td>
            <td class="val"><strong><?= htmlspecialchars($study['patient_name'] ?? '') ?></strong></td>
            <td class="label"><?= xlt('Medical Record / PID') ?>:</td>
            <td class="val">#<?= htmlspecialchars((string)$pid) ?></td>
        </tr>
        <tr>
            <td class="label"><?= xlt('Date of Birth') ?>:</td>
            <td class="val"><?= htmlspecialchars($study['patient_dob'] ?? '') ?></td>
            <td class="label"><?= xlt('Sex') ?>:</td>
            <td class="val"><?= htmlspecialchars($study['patient_sex'] ?? '') ?></td>
        </tr>
        <tr>
            <td class="label"><?= xlt('Recording Date') ?>:</td>
            <td class="val"><?= htmlspecialchars($recordingDate ? date('d/m/Y', strtotime($recordingDate)) : '--/--/----') ?></td>
            <td class="label"><?= xlt('Recording Time') ?>:</td>
            <td class="val"><?= htmlspecialchars($recordTime ?? '') ?></td>
        </tr>
        <tr>
            <td class="label"><?= xlt('Monitor Hours') ?>:</td>
            <td class="val"><?= htmlspecialchars($monitorHours) ?> h</td>
            <td class="label"><?= xlt('Mean SBP') ?>:</td>
            <td class="val"><?= number_format($meanSbp, 1) ?> mmHg</td>
        </tr>
        <tr>
            <td class="label"><?= xlt('Mean DBP') ?>:</td>
            <td class="val"><?= number_format($meanDbp, 1) ?> mmHg</td>
            <td class="label"><?= xlt('Heart Rate') ?>:</td>
            <td class="val"><?= htmlspecialchars($heartRate) ?> lpm</td>
        </tr>
        <tr>
            <td class="label"><?= xlt('Status') ?>:</td>
            <td class="val" colspan="3"><?= htmlspecialchars(ucfirst($status)) ?></td>
        </tr>
    </table>

    <!-- Disclaimer -->
    <div class="disclaimer">
        <?= xlt('Report generated on') ?>: <?= date('d/m/Y H:i:s') ?> | <?= xlt('OpenEMR') ?>
    </div>
</body>
</html>
<?php
// Send to Dompdf (OpenEMR handles the conversion)
?>