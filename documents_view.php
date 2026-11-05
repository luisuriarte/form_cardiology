<?php

/**
 * documents_view.php - Inline document viewer for cardiology attachments
 *
 * Serves a patient document directly in a new browser tab with
 * Content-Disposition: inline, instead of delegating to the core
 * controller.php?document&retrieve endpoint (which browsers may force to
 * download for PDF/images depending on MIME handling and CSP).
 *
 * Access is restricted to the active patient session. It verifies that the
 * requested document belongs to that patient before streaming the file.
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
use OpenEMR\Forms\Cardiology\CardDocuments;
use OpenEMR\Forms\Cardiology\CardiologyContainer;

require_once __DIR__ . '/lib/CardDocuments.php';
require_once __DIR__ . '/lib/CardiologyContainer.php';

function cardiologyViewError(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

$sessionPid = CardiologyContainer::getSessionPid();

$documentId = 0;
$rawDocId   = $_GET['document_id'] ?? $_GET['id'] ?? '';
if (is_numeric($rawDocId) && (int)$rawDocId > 0) {
    $documentId = (int)$rawDocId;
}

if ($sessionPid <= 0) {
    cardiologyViewError(403, xlt('Active patient session required.'));
}

if ($documentId <= 0) {
    cardiologyViewError(400, xlt('Invalid document identifier.'));
}

if (!AclMain::aclCheckCore('patients', 'docs')) {
    cardiologyViewError(403, xlt('Not authorized to access patient documents.'));
}

$docRow = sqlQuery(
    'SELECT id, foreign_id, name, mimetype, url, deleted FROM `documents` WHERE id = ? LIMIT 1',
    [$documentId]
);

if (!$docRow || empty($docRow['id']) || (int)($docRow['deleted'] ?? 0) !== 0) {
    cardiologyViewError(404, xlt('Document not found or has been removed.'));
}

if ((int)$docRow['foreign_id'] !== $sessionPid) {
    cardiologyViewError(403, xlt('Document does not belong to the selected patient.'));
}

require_once CardDocuments::getDocumentClassPath();

try {
    $doc = new \Document($documentId);
    if (!$doc->can_access()) {
        cardiologyViewError(403, xlt('Unauthorized attempt to access document.'));
    }
    $content = $doc->get_data();
} catch (\BadMethodCallException $e) {
    cardiologyViewError(404, xlt('Document not found or has been removed.'));
} catch (\RuntimeException $e) {
    cardiologyViewError(404, xlt('Document file not found.'));
} catch (\Throwable $e) {
    cardiologyViewError(500, xlt('An unexpected server error occurred.'));
}

if ($content === false || $content === null || $content === '') {
    cardiologyViewError(404, xlt('Document file not found.'));
}

$name = (string)($docRow['name'] ?? $doc->get_name() ?? ('document_' . $documentId));
$storedMime = (string)($docRow['mimetype'] ?? $doc->get_mimetype() ?? '');

$view = CardDocuments::resolveViewMime($storedMime, $name);
$mime = $view['mime'];

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($content));
header('Content-Disposition: inline; filename="' . addslashes($name) . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('X-Content-Type-Options: nosniff');

echo $content;
exit;
