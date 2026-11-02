<?php

/**
 * Template Partial: Study Document Attachments
 *
 * Renders the "Documents" section in a card's view.php screen.
 * Lists attached documents, opens the reusable upload dialog, and allows
 * linking existing patient documents.
 *
 * Variables expected in scope:
 *   $cardCode  string  Card code (e.g. 'echo', 'stress_test', 'ecg', 'preop_risk', 'fitness')
 *   $studyId   int     Study ID in the card's table
 *   $pid       int     Patient ID
 *   $study     array   Study record from DB (including status, finalized_at)
 *
 * Compatible with OpenEMR 8.2.0 - 8.5.0 and Bootstrap 4/5.
 *
 * @package   OpenEMR
 * @subpackage Cardiology
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Forms\Cardiology\CardDocuments;
use OpenEMR\Forms\Cardiology\CardRegistry;
use OpenEMR\Forms\Cardiology\CardiologyContainer;

require_once __DIR__ . '/../lib/CardDocuments.php';
require_once __DIR__ . '/../lib/CardRegistry.php';
require_once __DIR__ . '/../lib/CardiologyContainer.php';

$studyId  = (int)($studyId ?? 0);
$pid      = (int)($pid ?? 0);
$cardCode = (string)($cardCode ?? '');

if ($studyId <= 0 || $pid <= 0 || $cardCode === '') {
    return;
}

$hasDocsView = AclMain::aclCheckCore('patients', 'docs');
if (!$hasDocsView) {
    ?>
    <div class="cardiology-documents-section card mb-4">
        <div class="card-header bg-light">
            <i class="fa fa-paperclip me-1 text-muted" aria-hidden="true"></i>
            <strong><?php echo xlt('Attached Documents'); ?></strong>
        </div>
        <div class="card-body">
            <div class="alert alert-secondary mb-0">
                <?php echo xlt('You do not have permission to view patient documents.'); ?>
            </div>
        </div>
    </div>
    <?php
    return;
}

$userId   = (int)($_SESSION['authUserID'] ?? 0);
$registry = new CardRegistry();
$canWrite = $registry->canWrite($cardCode, $userId) && $hasDocsView;

$studyStatus = (string)($study['status'] ?? 'draft');
$isFinal     = ($studyStatus === 'final');
$finalizedAt = (string)($study['finalized_at'] ?? '');

$attachments      = CardDocuments::listForStudy($cardCode, $studyId, $pid);
$csrfToken        = CardiologyContainer::collectCsrfToken();
$apiUrl           = CardiologyContainer::getFormRootUrl() . '/documents_api.php';
$uploadUrl        = CardiologyContainer::getFormRootUrl() . '/documents_upload.php';
$retrieveUrlBase  = $GLOBALS['webroot'] . '/controller.php?document&retrieve&patient_id=' . urlencode((string)$pid) . '&as_file=false&document_id=';
$maxUploadBytes   = CardDocuments::getMaxUploadBytes();
$maxUploadText    = CardDocuments::formatBytes($maxUploadBytes);
$allowedExts      = CardDocuments::ALLOWED_EXTENSIONS;

$docsI18n = [
    'empty'               => xlt('No documents attached to this study.'),
    'open'                => xlt('Open'),
    'openTitle'           => xla('Open document'),
    'unlink'              => xlt('Unlink'),
    'unlinkTitle'         => xla('Unlink this attachment'),
    'unlinkConfirm'       => xla('Are you sure you want to unlink this document from the study? The file itself will remain safely in the patient documents.'),
    'postFinal'           => xlt('Added after finalization'),
    'postFinalTitle'      => xla('This document was attached after the study was finalized.'),
    'selectCandidate'     => xlt('-- Select an existing patient document --'),
    'noCandidates'        => xlt('No unlinked patient documents found.'),
    'candidatesAvailable' => xlt('-- Select a document (%d available) --'),
    'errorLoading'        => xlt('Error loading candidate documents.'),
    'uploadSuccess'       => xlt('%d document(s) uploaded successfully.'),
    'uploadPartial'       => xlt('%d uploaded, %d failed.'),
    'uploadFailed'        => xlt('No documents were uploaded.'),
    'uploadCancelled'     => xlt('Upload cancelled.'),
    'linkSuccess'         => xlt('Existing document linked successfully.'),
    'linkError'           => xlt('Failed to attach existing document.'),
    'unlinkSuccess'       => xlt('Attachment unlinked successfully.'),
    'unlinkError'         => xlt('Failed to unlink document.'),
    'networkError'        => xlt('Network error: %s'),
];
?>
<div class="card cardiology-documents-section mb-4"
     id="cardiology-documents-container"
     data-card-code="<?php echo attr($cardCode); ?>"
     data-study-id="<?php echo attr((string)$studyId); ?>"
     data-pid="<?php echo attr((string)$pid); ?>"
     data-is-final="<?php echo $isFinal ? '1' : '0'; ?>"
     data-finalized-at="<?php echo attr($finalizedAt); ?>"
     data-can-write="<?php echo $canWrite ? '1' : '0'; ?>"
     data-api-url="<?php echo attr($apiUrl); ?>"
     data-upload-url="<?php echo attr($uploadUrl); ?>"
     data-upload-multiple="1"
     data-upload-max-bytes="<?php echo attr((string)$maxUploadBytes); ?>"
     data-upload-max-text="<?php echo attr($maxUploadText); ?>"
     data-upload-allowed-exts="<?php echo attr(implode(',', $allowedExts)); ?>"
     data-retrieve-base="<?php echo attr($retrieveUrlBase); ?>"
     data-csrf-token="<?php echo attr($csrfToken); ?>">

    <div class="card-header d-flex align-items-center justify-content-between bg-light">
        <span class="cardiology-docs-title">
            <i class="fa fa-paperclip me-1 text-secondary" aria-hidden="true"></i>
            <strong><?php echo xlt('Attached Documents'); ?></strong>
            <span class="badge bg-secondary ms-1" id="cardiology-docs-count-badge">
                <?php echo count($attachments); ?>
            </span>
        </span>
        <?php if ($canWrite) : ?>
        <div class="cardiology-docs-actions btn-group">
            <button type="button" class="btn btn-sm btn-outline-primary" id="cardiology-btn-open-upload">
                <i class="fa fa-upload me-1" aria-hidden="true"></i> <?php echo xlt('Upload documents'); ?>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="cardiology-btn-open-picker">
                <i class="fa fa-folder-open me-1" aria-hidden="true"></i> <?php echo xlt('Attach Existing'); ?>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <div class="card-body">
        <div id="cardiology-docs-alert" class="alert d-none" role="alert" aria-live="polite"></div>

        <?php if ($canWrite) : ?>
        <p class="small text-muted mb-2">
            <?php echo sprintf(
                xlt('Allowed types: %s. Maximum size per file: %s.'),
                text(strtoupper(implode(', ', $allowedExts))),
                text($maxUploadText)
            ); ?>
        </p>

        <!-- Attach Existing Document Picker Panel (toggled via button) -->
        <div id="cardiology-picker-panel" class="card mb-3 bg-light border-light d-none">
            <div class="card-body p-3">
                <h6 class="card-title mb-2">
                    <i class="fa fa-link me-1 text-secondary" aria-hidden="true"></i> <?php echo xlt('Link Existing Patient Document'); ?>
                </h6>
                <div id="cardiology-picker-loading" class="text-muted small py-2 d-none">
                    <i class="fa fa-spinner fa-spin me-1" aria-hidden="true"></i> <?php echo xlt('Loading candidate documents...'); ?>
                </div>
                <div id="cardiology-picker-content">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-9 col-sm-12">
                            <select id="cardiology-candidate-select" class="form-select form-control form-control-sm">
                                <option value=""><?php echo xlt('-- Select an existing patient document --'); ?></option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-12 text-md-end">
                            <button type="button" class="btn btn-sm btn-secondary" id="cardiology-btn-confirm-link" disabled>
                                <i class="fa fa-link me-1" aria-hidden="true"></i> <?php echo xlt('Attach'); ?>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary ms-1" id="cardiology-picker-cancel-btn">
                                <?php echo xlt('Cancel'); ?>
                            </button>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-1">
                        <?php echo xlt('Lists up to 50 unlinked documents belonging to this patient.'); ?>
                    </small>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Attachments Table -->
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" id="cardiology-docs-table">
                <thead class="table-light">
                    <tr>
                        <th scope="col"><?php echo xlt('Document Title'); ?></th>
                        <th scope="col"><?php echo xlt('Category'); ?></th>
                        <th scope="col"><?php echo xlt('Date'); ?></th>
                        <th scope="col"><?php echo xlt('Uploaded By'); ?></th>
                        <th scope="col"><?php echo xlt('Size'); ?></th>
                        <th scope="col" class="text-end"><?php echo xlt('Actions'); ?></th>
                    </tr>
                </thead>
                <tbody id="cardiology-docs-tbody">
                    <?php if (empty($attachments)) : ?>
                    <tr id="cardiology-docs-empty-row">
                        <td colspan="6" class="text-center text-muted fst-italic py-3">
                            <?php echo xlt('No documents attached to this study.'); ?>
                        </td>
                    </tr>
                    <?php else : ?>
                        <?php foreach ($attachments as $att) :
                            $docId        = (int)$att['document_id'];
                            $linkId       = (int)$att['link_id'];
                            $docName      = (string)$att['doc_name'];
                            $categoryName = (string)($att['category_name'] ?? '');
                            $docDate      = (string)($att['doc_date'] ?? $att['linked_at'] ?? '');
                            $uploadedBy   = (string)($att['created_by'] ?? '');
                            $sizeBytes    = (int)($att['size'] ?? 0);
                            $sizeFormatted = CardDocuments::formatBytes($sizeBytes);
                            $linkedAt     = (string)($att['linked_at'] ?? '');

                            $postFinalNote = false;
                            if ($isFinal && $finalizedAt !== '' && $linkedAt !== '' && $linkedAt > $finalizedAt) {
                                $postFinalNote = true;
                            }

                            $retrieveUrl = $retrieveUrlBase . urlencode((string)$docId);
                        ?>
                        <tr data-link-id="<?php echo attr((string)$linkId); ?>" data-doc-id="<?php echo attr((string)$docId); ?>">
                            <td>
                                <i class="fa fa-file-text-o me-1 text-muted" aria-hidden="true"></i>
                                <strong><?php echo text($docName); ?></strong>
                                <?php if ($postFinalNote) : ?>
                                <span class="badge bg-info text-dark ms-1"
                                      title="<?php echo xla('This document was attached after the study was finalized.'); ?>">
                                    <i class="fa fa-clock-o me-1" aria-hidden="true"></i>
                                    <?php echo xlt('Added after finalization'); ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?php echo text(xl($categoryName)); ?>
                                </span>
                            </td>
                            <td class="small text-muted">
                                <?php echo text($docDate); ?>
                            </td>
                            <td class="small">
                                <?php echo text($uploadedBy ?: '—'); ?>
                            </td>
                            <td class="small text-muted">
                                <?php echo text($sizeFormatted); ?>
                            </td>
                            <td class="text-end">
                                <a href="<?php echo attr($retrieveUrl); ?>"
                                   target="_blank"
                                   rel="noopener"
                                   class="btn btn-xs btn-outline-primary me-1"
                                   title="<?php echo xla('Open document'); ?>">
                                    <i class="fa fa-external-link" aria-hidden="true"></i>
                                    <?php echo xlt('Open'); ?>
                                </a>
                                <?php if (!$isFinal && $canWrite) : ?>
                                <button type="button"
                                        class="btn btn-xs btn-outline-danger cardiology-btn-unlink"
                                        data-link-id="<?php echo attr((string)$linkId); ?>"
                                        title="<?php echo xla('Unlink this attachment'); ?>">
                                    <i class="fa fa-unlink" aria-hidden="true"></i>
                                    <?php echo xlt('Unlink'); ?>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script type="application/json" id="cardiology-docs-i18n"><?php echo json_encode($docsI18n, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?></script>
<script src="<?php echo attr(CardiologyContainer::getWebRootUrl()); ?>/js/cardiology-upload.js?v=<?php echo attr($GLOBALS['v_js_includes'] ?? ''); ?>"></script>
