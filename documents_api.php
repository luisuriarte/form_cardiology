<?php

/**
 * documents_api.php - Document attachment API endpoint
 *
 * Handles listing attachments, linking existing patient documents, and
 * unlinking attachments from draft studies. Uploads are handled exclusively by
 * documents_upload.php through CardDocuments::storeUploadedFile().
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

use OpenEMR\Forms\Cardiology\CardDocuments;
use OpenEMR\Forms\Cardiology\CardInput;
use OpenEMR\Forms\Cardiology\CardiologyContainer;

require_once __DIR__ . '/lib/CardDocuments.php';
require_once __DIR__ . '/lib/CardInput.php';
require_once __DIR__ . '/lib/CardiologyContainer.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => xlt('Method Not Allowed. Only POST requests are permitted.'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$csrfToken = $_POST['csrf_token_form'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!CardiologyContainer::verifyCsrfToken((string)$csrfToken)) {
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
        case 'list':
            if ($studyId === null || $cardCode === '') {
                throw new \InvalidArgumentException(xlt('Missing study identifier or card code.'));
            }

            $documents = CardDocuments::listForStudy($cardCode, $studyId, $sessionPid);

            echo json_encode([
                'success'   => true,
                'documents' => $documents,
                'count'     => count($documents),
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
        'message' => xlt('An unexpected server error occurred.'),
    ], JSON_UNESCAPED_UNICODE);
}
