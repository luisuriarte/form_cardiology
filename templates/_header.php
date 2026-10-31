<?php
/**
 * Template partial: patient + encounter header bar.
 *
 * Expects: $pid (int), $encounter (int) to be defined in the including scope.
 * Uses standard OpenEMR patient functions from library/patient.inc.php which
 * is loaded transitively through globals.php.
 *
 * @package   OpenEMR
 * @subpackage Cardiology
 */

// Fetch minimal patient demographics for the header.
$patientRow = sqlQuery(
    'SELECT fname, mname, lname, DOB FROM `patient_data` WHERE pid = ? LIMIT 1',
    [(int)$pid]
);
$patientName = '';
if (!empty($patientRow)) {
    $patientName = trim(
        text($patientRow['fname'] ?? '')
        . ' '
        . text($patientRow['mname'] ?? '')
        . ' '
        . text($patientRow['lname'] ?? '')
    );
}
?>
<div class="row cardiology-header mb-3">
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <h4 class="mb-1">
                <i class="fa fa-heart text-danger me-2" aria-hidden="true"></i>
                <?php echo xlt('Cardiology'); ?>
            </h4>
            <div class="cardiology-header-meta text-muted small">
                <span class="me-3">
                    <strong><?php echo xlt('Patient'); ?>:</strong>
                    <?php echo $patientName ?: text((string)$pid); ?>
                    (<?php echo xlt('ID'); ?>: <?php echo text((string)$pid); ?>)
                </span>
                <span>
                    <strong><?php echo xlt('Encounter'); ?>:</strong>
                    <?php echo text((string)$encounter); ?>
                </span>
            </div>
        </div>
        <hr class="mt-2 mb-0">
    </div>
</div>

