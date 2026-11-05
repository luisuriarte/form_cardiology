<?php

/**
 * Preop Risk card – print.php (modernized)
 *
 * Renders a clean printable report for a Preoperative Risk Assessment study.
 * Opens in a new tab (target="_blank").
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Preop_Risk
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
// Institutional logo for printable reports (Dompdf-safe data URI)
// ---------------------------------------------------------------------------
require_once(__DIR__ . '/../../lib/CardPrintAssets.php');
require_once(__DIR__ . '/../../lib/CardPrintReport.php');

$logoBase64 = \OpenEMR\Forms\Cardiology\CardPrintAssets::logoDataUri();

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
    'SELECT * FROM `form_card_preop_risk` WHERE id = ? AND pid = ? AND activity = 1',
    [$studyId, $pid]
);
if (!$study || !isset($study['id'])) {
    die(xlt('Study not found or has been deleted.'));
}

// Patient + encounter via OpenEMR services (PatientService / EncounterService),
// with direct-query fallback inside CardPrintReport. Populates the legacy
// $study[...] keys the patient box below already expects.
$printPatient = \OpenEMR\Forms\Cardiology\CardPrintReport::patient($pid);
$printEncounter = \OpenEMR\Forms\Cardiology\CardPrintReport::encounter($pid, (int)($study['encounter'] ?? $encounter));
$study['patient_name'] = \OpenEMR\Forms\Cardiology\CardPrintReport::patientName($printPatient);
$study['patient_dob'] = \OpenEMR\Forms\Cardiology\CardPrintReport::formatValue($printPatient['DOB'] ?? null);
$study['patient_sex'] = (string)($printPatient['sex'] ?? '');
$study['patient_dni'] = (string)($printPatient['ss'] ?? $printPatient['pubpid'] ?? '');
$study['patient_age'] = \OpenEMR\Forms\Cardiology\CardPrintReport::patientAge($printPatient);
$study['encounter_label'] = \OpenEMR\Forms\Cardiology\CardPrintReport::formatValue($printEncounter['reason'] ?? null);
$study['batch_date_formatted'] = \OpenEMR\Forms\Cardiology\CardPrintReport::formatValue($printEncounter['date'] ?? null);


// ---- Preop Risk-specific fields ----
$assessmentDate = $study['assessment_date'] ?? '';
$asaClass       = $study['asa_class'] ?? '';
$procedure      = $study['procedure'] ?? '';
$anesthesiaType = $study['anesthesia_type'] ?? '';
$riskFactors    = $study['risk_factors'] ?? '';
$functionalCapacity = (int)($study['functional_capacity'] ?? 0);
$status        = $study['status'] ?? '';

// ---- Build HTML for Dompdf ----
ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($study['report_title'] ?? xlt('Preoperative Risk Report')) ?> - OpenEMR</title>
    <style>
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
        .header {
            display: table;
            width: 100%;
            table-layout: fixed;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }
        .header-left,
        .header-right {
            display: table-cell;
            vertical-align: middle;
        }
        .header-left {
            width: 62%;
        }
        .header-right {
            width: 38%;
            text-align: right;
            color: #64748b;
            font-size: 11px;
        }
        .header-right .sub {
            font-size: 10.5px;
        }
        .logo {
            text-align: center;
            margin-bottom: 9px;
        }
        .logo img {
            max-height: 90px;
            max-width: 320px;
            height: auto;
            display: inline-block;
        }
        .clinic-name {
            font-size: 20px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            margin: 0;
        }
        .clinic-sub {
            font-size: 10.5px;
            color: #64748b;
            margin: 2px 0 0 0;
        }
        .report-title {
            text-align: center;
            background-color: #f0f9ff;
            border: 1px solid #bae6fd;
            color: #0369a1;
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 0.5px;
            padding: 7px 10px;
            margin: 12px 0 14px 0;
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
            padding: 5px 8px;
            font-size: 12px;
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
            page-break-after: avoid;
            font-size: 13px;
            font-weight: bold;
            color: #0369a1;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 3px;
            margin-top: 12px;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        .report-text {
            font-size: 12px;
            color: #334155;
            line-height: 1.55;
            background-color: #ffffff;
            padding: 7px 9px;
            border: 1px solid #f1f5f9;
            border-radius: 3px;
            margin-bottom: 6px;
        }
        .parameter-row {
            display: flex;
            justify-content: space-between;
            margin: 4px 0;
            font-size: 12px;
        }
        .parameter-row .label {
            color: #64748b;
        }
        .parameter-row .value {
            color: #0f172a;
            font-weight: 500;
        }
        .disclaimer {
            page-break-inside: avoid;
            font-size: 9.5px;
            color: #94a3b8;
            text-align: center;
            margin-top: 18px;
        }
    </style>
</head>
<body>

    <?= \OpenEMR\Forms\Cardiology\CardPrintReport::toolbarHtml() ?>

    <!-- Institutional Logo + Header -->
    <?php if ($logoBase64): ?>
        <div class="logo"><img src="<?= htmlspecialchars($logoBase64) ?>" alt="Logo"></div>
    <?php endif; ?>
    <div class="header">
        <div class="header-left">
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
        <?= htmlspecialchars(xlt('PREOPERATIVE RISK ASSESSMENT REPORT')) ?>
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
            <td class="label"><?= xlt('Assessment Date') ?>:</td>
            <td class="val"><?= htmlspecialchars($assessmentDate ? date('d/m/Y', strtotime($assessmentDate)) : '--/--/----') ?></td>
            <td class="label"><?= xlt('ASA Class') ?>:</td>
            <td class="val"><?= htmlspecialchars($asaClass) ?></td>
        </tr>
        <tr>
            <td class="label"><?= xlt('Procedure') ?>:</td>
            <td class="val" colspan="3"><?= htmlspecialchars($procedure) ?></td>
        </tr>
        <tr>
            <td class="label"><?= xlt('Anesthesia Type') ?>:</td>
            <td class="val" colspan="3"><?= htmlspecialchars($anesthesiaType) ?></td>
        </tr>
        <tr>
            <td class="label"><?= xlt('Risk Factors') ?>:</td>
            <td class="val" colspan="3"><?= htmlspecialchars($riskFactors) ?></td>
        </tr>
        <tr>
            <td class="label"><?= xlt('Functional Capacity') ?>:</td>
            <td class="val"><?= htmlspecialchars($functionalCapacity) ?> METs</td>
            <td class="label"><?= xlt('Status') ?>:</td>
            <td class="val" colspan="2"><?= htmlspecialchars(ucfirst($status)) ?></td>
        </tr>
    </table>

        <!-- All study fields (complete record) -->
    <?= \OpenEMR\Forms\Cardiology\CardPrintReport::genericSections($study, xlt('Study Data')) ?>

    <!-- Disclaimer -->
    <div class="disclaimer">
        <?= xlt('Report generated on') ?>: <?= date('d/m/Y H:i:s') ?> | <?= xlt('OpenEMR') ?>
    </div>
</body>
</html>
<?php
// Download as a real PDF file when ?download=1 (Dompdf, server-side).
// Otherwise the buffered HTML is flushed and shown in the browser.
if (($_GET['download'] ?? '') === '1') {
    $reportHtml = (string)ob_get_clean();
    \OpenEMR\Forms\Cardiology\CardPrintReport::downloadPdf(
        $reportHtml,
        'PreoperativeRisk_study_' . $studyId . '_encounter_' . $encounter . '.pdf'
    );
}
?>