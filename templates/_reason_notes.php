<?php
/**
 * Template partial: reason and notes fields.
 *
 * Expects:
 *   $container  (array|null)  – existing container row, or null for new form.
 *
 * @package   OpenEMR
 * @subpackage Cardiology
 */

$currentReason = ($container !== null) ? ($container['reason'] ?? '') : '';
$currentNotes  = ($container !== null) ? ($container['notes']  ?? '') : '';
?>
<div class="row mb-3">
    <div class="col-12 col-md-6">
        <div class="form-group">
            <label for="cardiology_reason" class="font-weight-bold">
                <?php echo xlt('Reason for Visit'); ?>:
            </label>
            <textarea id="cardiology_reason"
                      name="reason"
                      class="form-control"
                      rows="3"
                      placeholder="<?php echo xla('Enter the reason for the cardiology visit'); ?>"
            ><?php echo text($currentReason); ?></textarea>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="form-group">
            <label for="cardiology_notes" class="font-weight-bold">
                <?php echo xlt('Visit Notes'); ?>:
            </label>
            <textarea id="cardiology_notes"
                      name="notes"
                      class="form-control"
                      rows="3"
                      placeholder="<?php echo xla('Shared notes visible to all cards in this encounter'); ?>"
            ><?php echo text($currentNotes); ?></textarea>
        </div>
    </div>
</div>

