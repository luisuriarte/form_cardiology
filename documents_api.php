<?php

/**
 * documents_api.php - Document Attachment REST API Endpoint
 *
 * Handles AJAX requests for uploading documents, linking existing patient documents,
 * unlinking attachments from drafts, and listing candidate patient documents.
 *
 * Enforces POST method, CSRF tokens, strict parameter validation, and core permissions.
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

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Forms\Cardiology\CardDocuments;
use OpenEMR\Forms\Cardiology\CardInput;
use OpenEMR\Forms\Cardiology\CardiologyContainer;

require_once __DIR__ . '/lib/CardDocuments.php';
require_once __DIR__ . '/lib/CardInput.php';
require_once __DIR__ . '/lib/CardiologyContainer.php';

header('Content-Type: application/json; charset=utf-8');

// Strictly enforce POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => xlt('Method Not Allowed. Only POST requests are permitted.'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// CSRF Verification (checks form post and X-CSRF-Token header)
$csrfToken = $_POST['csrf_token_form'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!CsrfUtils::verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => xlt('Security token expired or invalid (CSRF failure). Please refresh the page.'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$sessionPid = CardiologyContainer::getSessionPid();
if ($sessionPid <= 0) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => xlt('Active patient session required.'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$action   = CardInput::text($_POST['action'] ?? '') ?? '';
$cardCode = CardInput::text($_POST['card_code'] ?? '') ?? '';
$studyId  = CardInput::int($_POST['study_id'] ?? null, 'study_id', 1);

try {
    switch ($action) {
        case 'upload':
            if ($studyId === null || $cardCode === '') {
                throw new \InvalidArgumentException(xlt('Missing study identifier or card code.'));
            }

            // Normalize $_FILES to process multiple or single file uploads
            $filesToProcess = [];
            if (isset($_FILES['doc_files']) && is_array($_FILES['doc_files']['name'])) {
                $count = count($_FILES['doc_files']['name']);
                for ($i = 0; $i < $count; $i++) {
                    if (empty($_FILES['doc_files']['name'][$i])) {
                        continue;
                    }
                    $filesToProcess[] = [
                        'name'     => $_FILES['doc_files']['name'][$i],
                        'type'     => $_FILES['doc_files']['type'][$i] ?? '',
                        'tmp_name' => $_FILES['doc_files']['tmp_name'][$i] ?? '',
                        'error'    => (int)($_FILES['doc_files']['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                        'size'     => (int)($_FILES['doc_files']['size'][$i] ?? 0),
                    ];
                }
            } elseif (isset($_FILES['doc_files'])) {
                $filesToProcess[] = $_FILES['doc_files'];
            } elseif (isset($_FILES['doc_file'])) {
                $filesToProcess[] = $_FILES['doc_file'];
            }

            if (empty($filesToProcess)) {
                throw new \InvalidArgumentException(xlt('No file was selected for upload.'));
            }

            $uploadedCount = 0;
            $uploadErrors  = [];

            foreach ($filesToProcess as $file) {
                try {
                    CardDocuments::attachUploaded($cardCode, $studyId, $sessionPid, $file);
                    $uploadedCount++;
                } catch (\Throwable $fe) {
                    $uploadErrors[] = $fe->getMessage();
                }
            }

            if ($uploadedCount === 0 && !empty($uploadErrors)) {
                throw new \RuntimeException(implode(' ', $uploadErrors));
            }

            $currentAttachments = CardDocuments::listForStudy($cardCode, $studyId, $sessionPid);
            $msg = ($uploadedCount === 1)
                ? xlt('Document attached successfully.')
                : sprintf(xlt('%d documents attached successfully.'), $uploadedCount);

            if (!empty($uploadErrors)) {
                $msg .= ' ' . xlt('Some files failed:') . ' ' . implode(' ', $uploadErrors);
            }

            echo json_encode([
                'success'     => true,
                'message'     => $msg,
                'documents'   => $currentAttachments,
                'count'       => count($currentAttachments),
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'attach_existing':
            if ($studyId === null || $cardCode === '') {
                throw new \InvalidArgumentException(xlt('Missing study identifier or card code.'));
            }
            $documentId = CardInput::int($_POST['document_id'] ?? null, 'document_id', 1);
            if ($documentId === null) {
                throw new \InvalidArgumentException(xlt('Missing document identifier.'));
            }

            CardDocuments::attachExisting($cardCode, $studyId, $sessionPid, $documentId);
            $currentAttachments = CardDocuments::listForStudy($cardCode, $studyId, $sessionPid);

            echo json_encode([
                'success'   => true,
                'message'   => xlt('Existing document linked successfully.'),
                'documents' => $currentAttachments,
                'count'     => count($currentAttachments),
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'unlink':
            $linkId = CardInput::int($_POST['link_id'] ?? null, 'link_id', 1);
            if ($linkId === null) {
                throw new \InvalidArgumentException(xlt('Missing link identifier.'));
            }

            CardDocuments::unlink($linkId, $sessionPid);

            $currentAttachments = ($studyId !== null && $cardCode !== '')
                ? CardDocuments::listForStudy($cardCode, $studyId, $sessionPid)
                : [];

            echo json_encode([
                'success'   => true,
                'message'   => xlt('Attachment unlinked successfully.'),
                'documents' => $currentAttachments,
                'count'     => count($currentAttachments),
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'list_candidates':
            if ($studyId === null || $cardCode === '') {
                throw new \InvalidArgumentException(xlt('Missing study identifier or card code.'));
            }

            $candidates = CardDocuments::listCandidates($cardCode, $studyId, $sessionPid);

            echo json_encode([
                'success'    => true,
                'candidates' => $candidates,
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            throw new \InvalidArgumentException(xlt('Invalid or unrecognized API action.'));
    }
} catch (\InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
} catch (\RuntimeException $e) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => xlt('An unexpected server error occurred: ') . text($e->getMessage()),
    ], JSON_UNESCAPED_UNICODE);
}

