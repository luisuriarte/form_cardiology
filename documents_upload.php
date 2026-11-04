<?php

/**
 * documents_upload.php - Cardiology document upload dialog
 *
 * GET  renders the iframe/dialog page.
 * POST accepts multipart uploads and returns the documented JSON result.
 *
 * No callback names, JavaScript, table names, or column names are accepted from
 * the request. Every file is stored through CardDocuments::storeUploadedFile().
 *
 * Compatible with OpenEMR 8.2.0 - 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

if (file_exists(__DIR__ . '/../../globals.php')) {
    require_once(__DIR__ . '/../../globals.php');
} elseif (file_exists(__DIR__ . '/../../../globals.php')) {
    require_once(__DIR__ . '/../../../globals.php');
}

use OpenEMR\Forms\Cardiology\CardDocumentCategories;
use OpenEMR\Forms\Cardiology\CardDocuments;
use OpenEMR\Forms\Cardiology\CardInput;
use OpenEMR\Forms\Cardiology\CardiologyContainer;

require_once __DIR__ . '/lib/CardDocumentCategories.php';
require_once __DIR__ . '/lib/CardDocuments.php';
require_once __DIR__ . '/lib/CardInput.php';
require_once __DIR__ . '/lib/CardiologyContainer.php';

/**
 * Sends the documented upload result and stops execution.
 *
 * @param list<array{id:int,name:string,category_id:int,linked:bool}> $documents
 * @param list<array{name:string,message:string}>                    $errors
 * @param list<array<string,mixed>>                                   $attachments
 */
function cardiologyUploadRespond(
    string $status,
    array $documents = [],
    array $errors = [],
    int $httpStatus = 200,
    array $attachments = []
): never {
    http_response_code($httpStatus);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'source'      => 'cardiology-upload',
        'status'      => $status,
        'documents'   => $documents,
        'errors'      => $errors,
        'attachments' => array_values($attachments),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Reads a positive integer request value without leaking parser messages.
 */
function cardiologyUploadInt(string $key, ?int $default = null): ?int
{
    $raw = $_POST[$key] ?? $_GET[$key] ?? null;
    if ($raw === null || $raw === '') {
        return $default;
    }

    try {
        return CardInput::int($raw, $key, 1);
    } catch (\InvalidArgumentException $e) {
        return null;
    }
}

/**
 * Returns the display label and CSS class for a document MIME type.
 *
 * @return array{0:string,1:string}
 */
function cardiologyUploadMimeBadge(string $mime): array
{
    $mime = strtolower($mime);
    if (str_contains($mime, 'pdf')) {
        return ['PDF', 'cardiology-mime-pdf'];
    }
    if (str_contains($mime, 'image')) {
        return [xlt('IMAGE'), 'cardiology-mime-image'];
    }
    if (str_contains($mime, 'dicom')) {
        return ['DICOM', 'cardiology-mime-dicom'];
    }

    return [xlt('OTHER'), 'cardiology-mime-other'];
}

/**
 * Renders the right-hand documents table body.
 *
 * @param list<array<string,mixed>> $attachments
 */
function cardiologyUploadRenderRows(array $attachments, string $retrieveBase): string
{
    if (empty($attachments)) {
        return '<tr><td colspan="6" class="text-center text-muted fst-italic py-4">'
            . text(xlt('No documents uploaded for this study.'))
            . '</td></tr>';
    }

    $html = '';
    foreach ($attachments as $att) {
        $docId       = (int)($att['document_id'] ?? 0);
        $docName     = (string)($att['doc_name'] ?? '');
        $category    = (string)($att['category_name'] ?? '');
        $docDate     = substr((string)($att['doc_date'] ?? $att['linked_at'] ?? ''), 0, 16);
        $sizeBytes   = (int)($att['size'] ?? 0);
        $mime        = (string)($att['mimetype'] ?? '');
        $retrieveUrl = $retrieveBase . rawurlencode((string)$docId);

        [$typeLabel, $typeClass] = cardiologyUploadMimeBadge($mime);

        $html .= '<tr data-doc-id="' . attr((string)$docId) . '">'
            . '<td class="text-break">' . text($docName) . '</td>'
            . '<td><span class="text-secondary small">' . text(xl($category)) . '</span></td>'
            . '<td><span class="cardiology-mime-badge ' . attr($typeClass) . '">' . text($typeLabel) . '</span></td>'
            . '<td class="text-end small text-muted">' . text(CardDocuments::formatBytes($sizeBytes)) . '</td>'
            . '<td class="text-center small text-muted">' . text($docDate !== '' ? $docDate : '—') . '</td>'
            . '<td class="text-center">'
            . '<a href="' . attr($retrieveUrl) . '" target="_blank" rel="noopener"'
            . ' class="btn btn-sm btn-outline-primary"'
            . ' title="' . xla('Open document') . '"'
            . ' aria-label="' . xla('Open document') . '">'
            . '<i class="fa fa-eye" aria-hidden="true"></i>'
            . '</a>'
            . '</td>'
            . '</tr>';
    }

    return $html;
}

$isPost = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST');

if (!in_array(($_SERVER['REQUEST_METHOD'] ?? ''), ['GET', 'POST'], true)) {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'source'    => 'cardiology-upload',
        'status'    => 'error',
        'documents' => [],
        'errors'    => [['name' => '', 'message' => xlt('Method Not Allowed. Only GET and POST requests are permitted.')]],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($isPost) {
    $csrfToken = $_POST['csrf_token_form'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!CardiologyContainer::verifyCsrfToken((string)$csrfToken)) {
        cardiologyUploadRespond('error', [], [[
            'name'    => '',
            'message' => xlt('Security token expired or invalid (CSRF failure). Please refresh the page.'),
        ]], 403);
    }
}

$sessionPid = CardiologyContainer::getSessionPid();
if ($sessionPid <= 0) {
    if ($isPost) {
        cardiologyUploadRespond('error', [], [[
            'name'    => '',
            'message' => xlt('Active patient session required.'),
        ]], 403);
    }
    http_response_code(403);
    die(xlt('Active patient session required.'));
}

$pid = cardiologyUploadInt('pid', 0);
if ($pid === null || $pid !== $sessionPid) {
    if ($isPost) {
        cardiologyUploadRespond('error', [], [[
            'name'    => '',
            'message' => xlt('The patient identifier does not match the active patient session.'),
        ]], 403);
    }
    http_response_code(403);
    die(xlt('The patient identifier does not match the active patient session.'));
}

$categoryId = cardiologyUploadInt('category_id', null);
$studyId    = cardiologyUploadInt('study_id', null);
$cardCode   = CardInput::text($_POST['card_code'] ?? $_GET['card_code'] ?? '') ?? '';
$multiple   = cardiologyUploadInt('multiple', 1);
$multiple   = ($multiple === 1) ? 1 : 0;
$keepOpen   = (($_POST['keep_open'] ?? $_GET['keep_open'] ?? '') === '1');

if (($_POST['category_id'] ?? $_GET['category_id'] ?? '') !== '' && $categoryId === null) {
    if ($isPost) {
        cardiologyUploadRespond('error', [], [[
            'name'    => '',
            'message' => xlt('Invalid document category.'),
        ]], 400);
    }
    http_response_code(400);
    die(xlt('Invalid document category.'));
}

if (($_POST['study_id'] ?? $_GET['study_id'] ?? '') !== '' && $studyId === null) {
    if ($isPost) {
        cardiologyUploadRespond('error', [], [[
            'name'    => '',
            'message' => xlt('Invalid study identifier.'),
        ]], 400);
    }
    http_response_code(400);
    die(xlt('Invalid study identifier.'));
}

try {
    $context = CardDocuments::resolveUploadContext($pid, $categoryId, $cardCode, $studyId);
} catch (\InvalidArgumentException $e) {
    if ($isPost) {
        cardiologyUploadRespond('error', [], [['name' => '', 'message' => $e->getMessage()]], 400);
    }
    $uploadError = $e->getMessage();
} catch (\RuntimeException $e) {
    if ($isPost) {
        cardiologyUploadRespond('error', [], [['name' => '', 'message' => $e->getMessage()]], 403);
    }
    $uploadError = $e->getMessage();
} catch (\Throwable $e) {
    if ($isPost) {
        cardiologyUploadRespond('error', [], [[
            'name'    => '',
            'message' => xlt('An unexpected server error occurred.'),
        ]], 500);
    }
    $uploadError = xlt('An unexpected server error occurred.');
}

if ($isPost) {
    $filesToProcess = [];
    if (isset($_FILES['files']) && is_array($_FILES['files'])) {
        if (is_array($_FILES['files']['name'] ?? null)) {
            $count = count($_FILES['files']['name']);
            for ($i = 0; $i < $count; $i++) {
                if (empty($_FILES['files']['name'][$i])) {
                    continue;
                }
                $filesToProcess[] = [
                    'name'     => (string)$_FILES['files']['name'][$i],
                    'type'     => (string)($_FILES['files']['type'][$i] ?? ''),
                    'tmp_name' => (string)($_FILES['files']['tmp_name'][$i] ?? ''),
                    'error'    => (int)($_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                    'size'     => (int)($_FILES['files']['size'][$i] ?? 0),
                ];
            }
        } else {
            $filesToProcess[] = [
                'name'     => (string)($_FILES['files']['name'] ?? ''),
                'type'     => (string)($_FILES['files']['type'] ?? ''),
                'tmp_name' => (string)($_FILES['files']['tmp_name'] ?? ''),
                'error'    => (int)($_FILES['files']['error'] ?? UPLOAD_ERR_NO_FILE),
                'size'     => (int)($_FILES['files']['size'] ?? 0),
            ];
        }
    }

    if (empty($filesToProcess)) {
        cardiologyUploadRespond('error', [], [[
            'name'    => '',
            'message' => xlt('No file was selected for upload.'),
        ]], 400);
    }

    if (count($filesToProcess) > CardDocuments::MAX_FILES_PER_REQUEST) {
        cardiologyUploadRespond('error', [], [[
            'name'    => '',
            'message' => sprintf(xlt('Maximum %d files per request.'), CardDocuments::MAX_FILES_PER_REQUEST),
        ]], 400);
    }

    if ($multiple === 0 && count($filesToProcess) > 1) {
        cardiologyUploadRespond('error', [], [[
            'name'    => '',
            'message' => xlt('Only one file may be uploaded per request.'),
        ]], 400);
    }

    $documents = [];
    $errors    = [];

    foreach ($filesToProcess as $file) {
        try {
            $result = CardDocuments::storeUploadedFile(
                $pid,
                $file,
                $categoryId,
                $cardCode !== '' ? $cardCode : null,
                $studyId
            );

            $documents[] = [
                'id'          => (int)$result['document_id'],
                'name'        => (string)($result['name'] ?? $file['name']),
                'category_id' => (int)$result['category_id'],
                'linked'      => (bool)$result['linked'],
            ];
        } catch (\Throwable $e) {
            $errors[] = [
                'name'    => (string)$file['name'],
                'message' => $e->getMessage(),
            ];
        }
    }

    $attachments = [];
    if ($context['card_code'] !== null && $context['study_id'] !== null) {
        $attachments = CardDocuments::listForStudy(
            (string)$context['card_code'],
            (int)$context['study_id'],
            $pid
        );
    }

    if (empty($documents)) {
        cardiologyUploadRespond('error', [], $errors, 200, $attachments);
    }
    if (!empty($errors)) {
        cardiologyUploadRespond('partial', $documents, $errors, 200, $attachments);
    }
    cardiologyUploadRespond('ok', $documents, [], 200, $attachments);
}

// GET: render the dialog.
$csrfToken     = CardiologyContainer::collectCsrfToken();
$maxBytes      = CardDocuments::getMaxUploadBytes();
$maxText       = CardDocuments::formatBytes($maxBytes);
$maxFiles      = $multiple === 1 ? CardDocuments::MAX_FILES_PER_REQUEST : 1;
$allowedExts   = CardDocuments::ALLOWED_EXTENSIONS;
$uploadUrl     = CardiologyContainer::getFormRootUrl() . '/documents_upload.php';
$formRoot      = CardiologyContainer::getWebRootUrl();
$uploadError   = $uploadError ?? '';
$context       = $context ?? null;
$treeHtml      = CardDocumentCategories::renderTreeHtml();

$defaultCategoryId   = 0;
$defaultCategoryName = '';
if ($context !== null) {
    $candidate = (int)$context['category_id'];
    if (CardDocumentCategories::isSelectable($candidate)) {
        $defaultCategoryId   = $candidate;
        $defaultCategoryName = CardDocumentCategories::getName($candidate);
    }
}

$attachments = [];
if ($context !== null && $context['card_code'] !== null && $context['study_id'] !== null) {
    $attachments = CardDocuments::listForStudy(
        (string)$context['card_code'],
        (int)$context['study_id'],
        $pid
    );
}

$retrieveBase = $GLOBALS['webroot'] . '/controller.php?document&retrieve&patient_id='
    . urlencode((string)$pid) . '&as_file=false&document_id=';

$config = [
    'pid'            => $pid,
    'categoryId'     => $defaultCategoryId,
    'cardCode'       => $context !== null ? (string)($context['card_code'] ?? '') : '',
    'studyId'        => ($context !== null && $context['study_id'] !== null) ? (int)$context['study_id'] : null,
    'multiple'       => $multiple,
    'keepOpen'       => $keepOpen,
    'csrfToken'      => $csrfToken,
    'maxUploadBytes' => $maxBytes,
    'maxFiles'       => $maxFiles,
    'allowedExts'    => array_values($allowedExts),
    'uploadUrl'      => $uploadUrl,
    'retrieveBase'   => $retrieveBase,
    'attachments'    => array_values($attachments),
];
?><!DOCTYPE html>
<html lang="<?php echo attr($_SESSION['language_direction'] ?? 'ltr') === 'rtl' ? 'ar' : 'en'; ?>">
<head>
    <title><?php echo xlt('Upload Documents'); ?></title>
    <?php \OpenEMR\Core\Header::setupHeader(['no_main-theme']); ?>
    <link rel="stylesheet" href="<?php echo attr($formRoot); ?>/css/cardiology.css">
</head>
<body class="cardiology-upload-dialog">
<div class="container-fluid py-2">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="mb-0">
            <i class="fa fa-cloud-upload me-1 text-primary" aria-hidden="true"></i>
            <?php echo xlt('Upload Documents'); ?>
        </h5>
        <button type="button" class="close" id="cardiology-upload-close-x" aria-label="<?php echo xla('Close'); ?>">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

    <?php if ($uploadError !== '') : ?>
        <div class="alert alert-danger" role="alert">
            <?php echo text($uploadError); ?>
        </div>
        <div class="text-right">
            <button type="button" class="btn btn-secondary" id="cardiology-upload-close"><?php echo xlt('Close'); ?></button>
        </div>
    <?php else : ?>
        <div id="cardiology-upload-alert" class="alert d-none" role="alert" aria-live="polite"></div>

        <div class="row">
            <div class="col-md-5 mb-3">
                <div class="card cardiology-upload-panel h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0"><?php echo xlt('Upload New Document'); ?></h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label font-weight-bold d-block" for="cardiology-tree">
                                <?php echo xlt('1. Choose destination folder'); ?>
                            </label>
                            <div class="cardiology-tree-container" id="cardiology-tree" role="tree"
                                 aria-label="<?php echo xla('Destination folder'); ?>">
                                <?php echo $treeHtml; ?>
                            </div>
                            <div class="cardiology-selection-display text-muted<?php echo $defaultCategoryId > 0 ? ' has-selection' : ''; ?>"
                                 id="cardiology-selection-display"
                                 aria-live="polite">
                                <?php if ($defaultCategoryId > 0) : ?>
                                    <i class="fa fa-folder-open me-1" aria-hidden="true"></i>
                                    <?php echo text($defaultCategoryName); ?>
                                <?php else : ?>
                                    <?php echo xlt('No folder selected'); ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label font-weight-bold d-block" for="cardiology-upload-input">
                                <?php echo xlt('2. Select file'); ?>
                            </label>
                            <div id="cardiology-upload-dropzone"
                                 class="cardiology-upload-dropzone"
                                 role="button"
                                 tabindex="0"
                                 aria-label="<?php echo xla('Drag and drop files here or press Enter to choose files'); ?>">
                                <input type="file"
                                       id="cardiology-upload-input"
                                       class="cardiology-upload-file-input"
                                       name="files[]"
                                       <?php echo $multiple === 1 ? 'multiple' : ''; ?>
                                       accept="<?php echo attr('.' . implode(',.', $allowedExts)); ?>">
                                <i class="fa fa-cloud-upload fa-2x text-primary mb-2" aria-hidden="true"></i>
                                <div class="font-weight-bold">
                                    <?php echo xlt('Drag file here or'); ?>
                                    <span class="text-primary"><?php echo xlt('click to browse'); ?></span>
                                </div>
                                <small class="text-muted d-block mt-2">
                                    <?php echo sprintf(
                                        xlt('Allowed types: %s. Maximum size per file: %s. Maximum files per upload: %d.'),
                                        text(strtoupper(implode(', ', $allowedExts))),
                                        text($maxText),
                                        $maxFiles
                                    ); ?>
                                </small>
                                <div class="cardiology-upload-selected-file d-none" id="cardiology-upload-file-name"></div>
                            </div>
                        </div>

                        <div id="cardiology-upload-queue" class="cardiology-upload-queue mb-3" aria-live="polite"></div>

                        <div id="cardiology-upload-summary" class="d-none mb-3" aria-live="polite"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-7 mb-3">
                <div class="card cardiology-upload-panel h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0"><?php echo xlt('Uploaded Documents'); ?></h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="cardiology-upload-docs-table">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col"><?php echo xlt('Name'); ?></th>
                                        <th scope="col"><?php echo xlt('Category'); ?></th>
                                        <th scope="col"><?php echo xlt('Type'); ?></th>
                                        <th scope="col" class="text-end"><?php echo xlt('Size'); ?></th>
                                        <th scope="col" class="text-center"><?php echo xlt('Date'); ?></th>
                                        <th scope="col" class="text-center"><?php echo xlt('Action'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="cardiology-upload-docs-tbody">
                                    <?php echo cardiologyUploadRenderRows($attachments, $retrieveBase); ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-between">
            <div>
                <button type="button" class="btn btn-outline-secondary" id="cardiology-upload-cancel">
                    <?php echo xlt('Cancel'); ?>
                </button>
                <button type="button" class="btn btn-outline-warning d-none" id="cardiology-upload-retry">
                    <?php echo xlt('Retry failed files'); ?>
                </button>
            </div>
            <div>
                <button type="button" class="btn btn-secondary d-none" id="cardiology-upload-close">
                    <?php echo xlt('Close'); ?>
                </button>
                <button type="button" class="btn btn-primary" id="cardiology-upload-submit" disabled>
                    <i class="fa fa-upload me-1" aria-hidden="true"></i>
                    <?php echo xlt('Upload'); ?>
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
window.CARD_UPLOAD_CONFIG = <?php echo json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
window.CARD_UPLOAD_I18N = {
    noDocuments: <?php echo json_encode(xl('No documents uploaded for this study.')); ?>,
    open: <?php echo json_encode(xl('Open')); ?>,
    openTitle: <?php echo json_encode(xla('Open document')); ?>,
    typePdf: <?php echo json_encode(xl('PDF')); ?>,
    typeImage: <?php echo json_encode(xl('IMAGE')); ?>,
    typeDicom: <?php echo json_encode(xl('DICOM')); ?>,
    typeOther: <?php echo json_encode(xl('OTHER')); ?>,
    noFolderSelected: <?php echo json_encode(xl('No folder selected')); ?>,
    selectFolderFirst: <?php echo json_encode(xl('Please select a destination folder first.')); ?>,
    pending: <?php echo json_encode(xl('Pending')); ?>,
    uploading: <?php echo json_encode(xl('Uploading')); ?>,
    uploaded: <?php echo json_encode(xl('Uploaded')); ?>,
    failed: <?php echo json_encode(xl('Failed')); ?>,
    remove: <?php echo json_encode(xl('Remove')); ?>,
    retry: <?php echo json_encode(xl('Retry')); ?>,
    cancel: <?php echo json_encode(xl('Cancel')); ?>,
    cancelUpload: <?php echo json_encode(xl('Cancel upload')); ?>,
    uploadCancelled: <?php echo json_encode(xl('Upload cancelled.')); ?>,
    noFileSelected: <?php echo json_encode(xl('No file was selected for upload.')); ?>,
    fileTooLarge: <?php echo json_encode(xl('The file exceeds the maximum upload limit.')); ?>,
    fileTypeNotAllowed: <?php echo json_encode(xl('File type not allowed.')); ?>,
    tooManyFiles: <?php echo json_encode(xl('Too many files selected.')); ?>,
    networkError: <?php echo json_encode(xl('Network error while uploading.')); ?>,
    uploadComplete: <?php echo json_encode(xl('Upload completed successfully.')); ?>,
    uploadPartial: <?php echo json_encode(xl('Some files were uploaded. Review the failed files below.')); ?>,
    uploadFailed: <?php echo json_encode(xl('Upload failed.')); ?>,
    close: <?php echo json_encode(xl('Close')); ?>
};
</script>
<script src="<?php echo attr($formRoot); ?>/js/cardiology-upload-dialog.js"></script>
</body>
</html>
<?php
exit;
