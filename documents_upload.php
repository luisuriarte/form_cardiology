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

use OpenEMR\Forms\Cardiology\CardDocuments;
use OpenEMR\Forms\Cardiology\CardInput;
use OpenEMR\Forms\Cardiology\CardiologyContainer;

require_once __DIR__ . '/lib/CardDocuments.php';
require_once __DIR__ . '/lib/CardInput.php';
require_once __DIR__ . '/lib/CardiologyContainer.php';

/**
 * Sends the documented upload result and stops execution.
 *
 * @param list<array{id:int,name:string,category_id:int,linked:bool}> $documents
 * @param list<array{name:string,message:string}>                    $errors
 */
function cardiologyUploadRespond(string $status, array $documents = [], array $errors = [], int $httpStatus = 200): never
{
    http_response_code($httpStatus);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'source'    => 'cardiology-upload',
        'status'    => $status,
        'documents' => $documents,
        'errors'    => $errors,
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

    if (empty($documents)) {
        cardiologyUploadRespond('error', [], $errors);
    }
    if (!empty($errors)) {
        cardiologyUploadRespond('partial', $documents, $errors);
    }
    cardiologyUploadRespond('ok', $documents, []);
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
$config        = [
    'pid'            => $pid,
    'categoryId'     => $context !== null ? (int)$context['category_id'] : 0,
    'cardCode'       => $context !== null ? (string)($context['card_code'] ?? '') : '',
    'studyId'        => ($context !== null && $context['study_id'] !== null) ? (int)$context['study_id'] : null,
    'multiple'       => $multiple,
    'csrfToken'      => $csrfToken,
    'maxUploadBytes' => $maxBytes,
    'maxFiles'       => $maxFiles,
    'allowedExts'    => array_values($allowedExts),
    'uploadUrl'      => $uploadUrl,
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

        <div id="cardiology-upload-dropzone"
             class="cardiology-upload-dropzone mb-3"
             role="button"
             tabindex="0"
             aria-label="<?php echo xla('Drag and drop files here or press Enter to choose files'); ?>">
            <i class="fa fa-cloud-upload fa-2x text-primary mb-2" aria-hidden="true"></i>
            <div class="font-weight-bold"><?php echo xlt('Drag and drop files here'); ?></div>
            <div class="small text-muted"><?php echo xlt('or click to choose files'); ?></div>
        </div>

        <div class="form-group">
            <label class="sr-only" for="cardiology-upload-input"><?php echo xlt('Select files'); ?></label>
            <input type="file"
                   id="cardiology-upload-input"
                   class="form-control-file"
                   name="files[]"
                   <?php echo $multiple === 1 ? 'multiple' : ''; ?>
                   accept="<?php echo attr('.' . implode(',.', $allowedExts)); ?>">
            <small class="form-text text-muted">
                <?php echo sprintf(
                    xlt('Allowed types: %s. Maximum size per file: %s. Maximum files per upload: %d.'),
                    text(strtoupper(implode(', ', $allowedExts))),
                    text($maxText),
                    $maxFiles
                ); ?>
            </small>
        </div>

        <div id="cardiology-upload-queue" class="cardiology-upload-queue mb-3" aria-live="polite"></div>

        <div id="cardiology-upload-summary" class="d-none mb-3" aria-live="polite"></div>

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
    noDocuments: <?php echo json_encode(xl('No documents attached to this study.')); ?>,
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
