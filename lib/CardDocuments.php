<?php

/**
 * CardDocuments - Service for managing document attachments on cardiology studies
 *
 * Links OpenEMR patient documents to clinical cardiology studies using the
 * card_documents join table, and stores uploads via the core Document class
 * under the dedicated Cardiology document categories.
 *
 * Compatible with OpenEMR 8.2.0 - 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Forms\Cardiology;

require_once __DIR__ . '/CardRegistry.php';
require_once __DIR__ . '/CardiologyContainer.php';
require_once __DIR__ . '/CardAudit.php';
require_once __DIR__ . '/CardInput.php';

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Database\QueryUtils;
use Throwable;

final class CardDocuments
{
    /** Category ID for Cardiology root branch */
    public const ROOT_CATEGORY_ID = 22000;

    /** Fallback category ID for unspecified cardiology documents */
    public const FALLBACK_CATEGORY_ID = 22007;

    /** Whitelisted file extensions */
    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'tif', 'tiff'];

    /** Map of extension to verified MIME types */
    public const ALLOWED_MIME_MAP = [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png'  => ['image/png', 'image/x-png'],
        'tif'  => ['image/tiff', 'image/x-tiff'],
        'tiff' => ['image/tiff', 'image/x-tiff'],
    ];

    /**
     * Resolves the target document category ID for a given card.
     * Checks card_modules.doc_category_id, verifies existence in categories,
     * falls back to 22007, or throws an actionable exception.
     *
     * @throws \RuntimeException If neither card category nor fallback exists in categories.
     */
    public static function getCategoryId(string $cardCode): int
    {
        $cardCode = CardInput::text($cardCode) ?? '';
        if ($cardCode !== '') {
            $row = sqlQuery(
                'SELECT doc_category_id FROM `card_modules` WHERE code = ? LIMIT 1',
                [$cardCode]
            );
            if ($row && !empty($row['doc_category_id'])) {
                $targetId = (int)$row['doc_category_id'];
                $catCheck = sqlQuery('SELECT id FROM `categories` WHERE id = ? LIMIT 1', [$targetId]);
                if ($catCheck && !empty($catCheck['id'])) {
                    return $targetId;
                }
            }
        }

        // Check fallback 22007
        $fallback = sqlQuery('SELECT id FROM `categories` WHERE id = ? LIMIT 1', [self::FALLBACK_CATEGORY_ID]);
        if ($fallback && !empty($fallback['id'])) {
            return self::FALLBACK_CATEGORY_ID;
        }

        throw new \RuntimeException(xlt('Document category not found. Please run sql/upgrade_003_documents.sql and rebuild the category tree.'));
    }

    /**
     * Lists active document attachments for a study.
     * Enforces patients/docs view permissions and patient scope.
     *
     * @return list<array<string, mixed>>
     */
    public static function listForStudy(string $cardCode, int $studyId, int $pid): array
    {
        $studyId = CardInput::int($studyId, 'study_id', 1);
        $pid     = CardInput::int($pid, 'pid', 1);
        if ($studyId === null || $pid === null) {
            return [];
        }

        if (!AclMain::aclCheckCore('patients', 'docs')) {
            return [];
        }

        $tableName = self::getStudyTableName($cardCode);
        if ($tableName === null) {
            return [];
        }

        // Verify study exists and belongs to pid
        $study = sqlQuery(
            "SELECT id FROM `{$tableName}` WHERE id = ? AND pid = ? AND activity = 1 LIMIT 1",
            [$studyId, $pid]
        );
        if (!$study || empty($study['id'])) {
            return [];
        }

        $sql = "SELECT cd.id AS link_id,
                       cd.card_code,
                       cd.study_id,
                       cd.pid,
                       cd.document_id,
                       cd.category_id,
                       cd.created_by,
                       cd.created_at AS linked_at,
                       cd.activity,
                       d.name AS doc_name,
                       d.mimetype,
                       d.size,
                       d.date AS doc_date,
                       d.url,
                       c.name AS category_name
                FROM `card_documents` cd
                INNER JOIN `documents` d ON d.id = cd.document_id
                LEFT JOIN `categories` c ON c.id = cd.category_id
                WHERE cd.card_code = ?
                  AND cd.study_id = ?
                  AND cd.pid = ?
                  AND cd.activity = 1
                  AND d.deleted = 0
                ORDER BY cd.id DESC";

        $res = sqlStatement($sql, [$cardCode, $studyId, $pid]);
        $list = [];
        while ($row = sqlFetchArray($res)) {
            $list[] = $row;
        }
        return $list;
    }

    /**
     * Attaches an uploaded file to an existing study.
     * Stores the physical file and document record using OpenEMR's core Document class,
     * files it under the Cardiology category, links it in card_documents, and logs an audit record.
     *
     * Allowed for both draft and final studies.
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @return array{success: bool, document_id: int, link_id: int, message: string}
     */
    public static function attachUploaded(string $cardCode, int $studyId, int $pid, array $file): array
    {
        self::verifySessionAndPermissions($cardCode, $pid);

        $studyId = CardInput::int($studyId, 'study_id', 1);
        if ($studyId === null) {
            throw new \InvalidArgumentException(xlt('Invalid study identifier.'));
        }

        $tableName = self::getStudyTableName($cardCode);
        if ($tableName === null) {
            throw new \InvalidArgumentException(xlt('Invalid card code.'));
        }

        $study = sqlQuery(
            "SELECT id, encounter, status, finalized_at FROM `{$tableName}` WHERE id = ? AND pid = ? AND activity = 1 LIMIT 1",
            [$studyId, $pid]
        );
        if (!$study || empty($study['id'])) {
            throw new \InvalidArgumentException(xlt('Study not found or has been deleted.'));
        }
        $encounter = (int)($study['encounter'] ?? 0);

        // Validate upload file structure
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $errCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
            throw new \InvalidArgumentException(self::getUploadErrorMessage($errCode, (string)($file['name'] ?? '')));
        }

        $filename = trim(basename((string)($file['name'] ?? '')));
        if ($filename === '') {
            throw new \InvalidArgumentException(xlt('Uploaded file must have a valid filename.'));
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException(
                sprintf(xlt('File type not allowed for "%s". Allowed extensions: %s.'), $filename, strtoupper(implode(', ', self::ALLOWED_EXTENSIONS)))
            );
        }

        $tmpPath = (string)($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath)) {
            throw new \InvalidArgumentException(xlt('Uploaded temporary file not found.'));
        }

        $fileSize = (int)($file['size'] ?? 0);
        if ($fileSize <= 0) {
            $fileSize = (int)filesize($tmpPath);
        }
        if ($fileSize <= 0) {
            throw new \InvalidArgumentException(sprintf(xlt('The file "%s" is empty.'), $filename));
        }

        $maxBytes = self::getMaxUploadBytes();
        if ($fileSize > $maxBytes) {
            throw new \InvalidArgumentException(
                sprintf(xlt('The file "%s" (%s) exceeds the maximum upload limit (%s).'), $filename, self::formatBytes($fileSize), self::formatBytes($maxBytes))
            );
        }

        // Verify content MIME type with mime_content_type (never trust client header)
        $detectedMime = mime_content_type($tmpPath) ?: '';
        $allowedMimes = self::ALLOWED_MIME_MAP[$ext] ?? [];
        if (!in_array($detectedMime, $allowedMimes, true)) {
            throw new \InvalidArgumentException(
                sprintf(xlt('File content type "%s" is not permitted or does not match extension for "%s".'), $detectedMime, $filename)
            );
        }

        $categoryId = self::getCategoryId($cardCode);
        $userId     = (int)($_SESSION['authUserID'] ?? 0);
        $username   = (string)($_SESSION['authUser'] ?? '');

        // Use OpenEMR core Document class to persist the physical file and core DB record
        require_once $GLOBALS['srcdir'] . '/classes/Document.class.php';
        $doc = new \Document();
        $fileContent = file_get_contents($tmpPath);
        if ($fileContent === false) {
            throw new \RuntimeException(sprintf(xlt('Unable to read temporary file "%s".'), $filename));
        }

        $errorMsg = $doc->createDocument(
            $pid,
            $categoryId,
            $filename,
            $detectedMime,
            $fileContent,
            '',
            1,
            $userId,
            $tmpPath,
            null,
            $studyId,
            $tableName
        );

        if (!empty($errorMsg)) {
            throw new \RuntimeException(sprintf(xlt('OpenEMR Document storage failed for "%s": %s'), $filename, text($errorMsg)));
        }

        $documentId = (int)$doc->get_id();
        if ($documentId <= 0) {
            throw new \RuntimeException(xlt('Failed to retrieve newly created document ID.'));
        }

        // Set encounter on the document if known
        if ($encounter > 0) {
            sqlStatement('UPDATE `documents` SET `encounter_id` = ? WHERE `id` = ?', [$encounter, $documentId]);
        }

        CardiologyContainer::startTransaction();
        try {
            $linkId = self::insertOrReactivateLink($cardCode, $studyId, $pid, $documentId, $categoryId, $username);
            CardiologyContainer::commitTransaction();

            CardAudit::log('doc_attach_upload', $cardCode, [
                'study_id' => $studyId,
                'doc_id'   => $documentId,
                'cat_id'   => $categoryId,
                'filesize' => $fileSize,
            ], true);

            return [
                'success'     => true,
                'document_id' => $documentId,
                'link_id'     => $linkId,
                'message'     => xlt('Document attached successfully.'),
            ];
        } catch (Throwable $e) {
            CardiologyContainer::rollbackTransaction();
            CardAudit::log('doc_attach_upload', $cardCode, [
                'study_id' => $studyId,
                'doc_id'   => $documentId,
                'cat_id'   => $categoryId,
            ], false);
            throw $e;
        }
    }

    /**
     * Links an existing document belonging to the same patient to the study.
     * Enforces that the document belongs to the session patient and is not deleted.
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public static function attachExisting(string $cardCode, int $studyId, int $pid, int $documentId): array
    {
        self::verifySessionAndPermissions($cardCode, $pid);

        $studyId    = CardInput::int($studyId, 'study_id', 1);
        $documentId = CardInput::int($documentId, 'document_id', 1);
        if ($studyId === null || $documentId === null) {
            throw new \InvalidArgumentException(xlt('Invalid study or document identifier.'));
        }

        $tableName = self::getStudyTableName($cardCode);
        if ($tableName === null) {
            throw new \InvalidArgumentException(xlt('Invalid card code.'));
        }

        $study = sqlQuery(
            "SELECT id, status FROM `{$tableName}` WHERE id = ? AND pid = ? AND activity = 1 LIMIT 1",
            [$studyId, $pid]
        );
        if (!$study || empty($study['id'])) {
            throw new \InvalidArgumentException(xlt('Study not found or has been deleted.'));
        }

        // Verify document belongs to the SAME patient and is not deleted
        $doc = sqlQuery(
            'SELECT id, foreign_id, deleted FROM `documents` WHERE id = ? LIMIT 1',
            [$documentId]
        );
        if (!$doc || empty($doc['id']) || (int)$doc['deleted'] !== 0) {
            throw new \InvalidArgumentException(xlt('Document not found or has been removed.'));
        }

        if ((int)$doc['foreign_id'] !== $pid) {
            throw new \InvalidArgumentException(xlt('Document does not belong to the selected patient. Cross-patient linking is prohibited.'));
        }

        // Determine existing category from categories_to_documents or default
        $catRow = sqlQuery(
            'SELECT category_id FROM `categories_to_documents` WHERE document_id = ? LIMIT 1',
            [$documentId]
        );
        $categoryId = !empty($catRow['category_id']) ? (int)$catRow['category_id'] : self::getCategoryId($cardCode);
        $username   = (string)($_SESSION['authUser'] ?? '');

        CardiologyContainer::startTransaction();
        try {
            $linkId = self::insertOrReactivateLink($cardCode, $studyId, $pid, $documentId, $categoryId, $username);
            CardiologyContainer::commitTransaction();

            CardAudit::log('doc_attach_existing', $cardCode, [
                'study_id' => $studyId,
                'doc_id'   => $documentId,
                'cat_id'   => $categoryId,
            ], true);

            return [
                'success' => true,
                'link_id' => $linkId,
                'message' => xlt('Document linked successfully.'),
            ];
        } catch (Throwable $e) {
            CardiologyContainer::rollbackTransaction();
            CardAudit::log('doc_attach_existing', $cardCode, [
                'study_id' => $studyId,
                'doc_id'   => $documentId,
                'cat_id'   => $categoryId,
            ], false);
            throw $e;
        }
    }

    /**
     * Unlinks an attachment from a study (soft delete activity = 0).
     * Only permitted for DRAFT studies. Finalized studies cannot have attachments removed.
     * Never deletes the physical document from OpenEMR.
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public static function unlink(int $linkId, int $pid): void
    {
        $linkId = CardInput::int($linkId, 'link_id', 1);
        if ($linkId === null) {
            throw new \InvalidArgumentException(xlt('Invalid link identifier.'));
        }

        $sessionPid = CardiologyContainer::getSessionPid();
        if ($pid <= 0 || $pid !== $sessionPid) {
            throw new \InvalidArgumentException(xlt('Invalid patient session.'));
        }

        $link = sqlQuery(
            'SELECT cd.id, cd.card_code, cd.study_id, cd.pid, cd.document_id, cd.category_id, cm.table_name
             FROM `card_documents` cd
             INNER JOIN `card_modules` cm ON cm.code = cd.card_code
             WHERE cd.id = ? AND cd.pid = ? AND cd.activity = 1 LIMIT 1',
            [$linkId, $pid]
        );

        if (!$link || empty($link['id'])) {
            throw new \InvalidArgumentException(xlt('Attachment link not found or already unlinked.'));
        }

        $cardCode   = (string)$link['card_code'];
        $studyId    = (int)$link['study_id'];
        $documentId = (int)$link['document_id'];
        $categoryId = (int)$link['category_id'];
        $tableName  = (string)$link['table_name'];

        $userId = (int)($_SESSION['authUserID'] ?? 0);
        $registry = new CardRegistry();
        if (!$registry->canWrite($cardCode, $userId) || !AclMain::aclCheckCore('patients', 'docs')) {
            throw new \RuntimeException(xlt('Not authorized to unlink documents.'));
        }

        if (!preg_match('/^form_card_[a-z0-9_]+$/', $tableName)) {
            throw new \RuntimeException(xlt('Invalid table name.'));
        }

        // Study status check: Unlinking is ONLY allowed for drafts!
        $study = sqlQuery(
            "SELECT status FROM `{$tableName}` WHERE id = ? AND pid = ? AND activity = 1 LIMIT 1",
            [$studyId, $pid]
        );
        if (!$study || empty($study['status'])) {
            throw new \InvalidArgumentException(xlt('Associated study not found.'));
        }

        if ($study['status'] === 'final') {
            throw new \RuntimeException(xlt('Documents cannot be unlinked from a finalized study. Finalized records are read-only.'));
        }

        CardiologyContainer::startTransaction();
        try {
            sqlStatement('UPDATE `card_documents` SET `activity` = 0 WHERE `id` = ?', [$linkId]);
            CardiologyContainer::commitTransaction();

            CardAudit::log('doc_unlink', $cardCode, [
                'study_id' => $studyId,
                'doc_id'   => $documentId,
                'cat_id'   => $categoryId,
            ], true);
        } catch (Throwable $e) {
            CardiologyContainer::rollbackTransaction();
            CardAudit::log('doc_unlink', $cardCode, [
                'study_id' => $studyId,
                'doc_id'   => $documentId,
                'cat_id'   => $categoryId,
            ], false);
            throw $e;
        }
    }

    /**
     * Lists candidate documents of the same patient not yet linked to this study (last 50).
     *
     * @return list<array<string, mixed>>
     */
    public static function listCandidates(string $cardCode, int $studyId, int $pid): array
    {
        $studyId = CardInput::int($studyId, 'study_id', 1);
        $pid     = CardInput::int($pid, 'pid', 1);
        if ($studyId === null || $pid === null) {
            return [];
        }

        if (!AclMain::aclCheckCore('patients', 'docs')) {
            return [];
        }

        $sessionPid = CardiologyContainer::getSessionPid();
        if ($pid !== $sessionPid) {
            return [];
        }

        $sql = "SELECT d.id,
                       d.name,
                       d.date,
                       d.mimetype,
                       d.size,
                       c.name AS category_name
                FROM `documents` d
                LEFT JOIN `categories_to_documents` c2d ON c2d.document_id = d.id
                LEFT JOIN `categories` c ON c.id = c2d.category_id
                WHERE d.foreign_id = ?
                  AND d.deleted = 0
                  AND NOT EXISTS (
                      SELECT 1 FROM `card_documents` cd
                      WHERE cd.document_id = d.id
                        AND cd.card_code = ?
                        AND cd.study_id = ?
                        AND cd.activity = 1
                  )
                ORDER BY d.id DESC
                LIMIT 50";

        $res = sqlStatement($sql, [$pid, $cardCode, $studyId]);
        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = $r;
        }
        return $rows;
    }

    /**
     * Formats bytes into a human-readable string.
     */
    public static function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = (int)floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        $size  = $bytes / (1024 ** $power);
        return round($size, 1) . ' ' . $units[$power];
    }

    /**
     * Determines maximum upload bytes enforced by PHP environment and core defaults.
     */
    public static function getMaxUploadBytes(): int
    {
        $uploadMax = self::iniToBytes((string)ini_get('upload_max_filesize'));
        $postMax   = self::iniToBytes((string)ini_get('post_max_size'));

        $limits = array_filter([$uploadMax, $postMax], static fn(int $b): bool => $b > 0);
        $minLimit = !empty($limits) ? min($limits) : (50 * 1024 * 1024);

        // Core ceiling of 50 MB
        return min($minLimit, 50 * 1024 * 1024);
    }

    /**
     * Inserts a new link or reactivates an existing soft-deleted link.
     * Prevents duplicate active links with a clear translated message.
     *
     * @throws \InvalidArgumentException
     */
    private static function insertOrReactivateLink(
        string $cardCode,
        int $studyId,
        int $pid,
        int $documentId,
        int $categoryId,
        string $username
    ): int {
        $existing = sqlQuery(
            'SELECT id, activity FROM `card_documents`
             WHERE card_code = ? AND study_id = ? AND document_id = ? LIMIT 1',
            [$cardCode, $studyId, $documentId]
        );

        if ($existing && !empty($existing['id'])) {
            if ((int)$existing['activity'] === 1) {
                throw new \InvalidArgumentException(xlt('This document is already attached to this study.'));
            }

            // Reactivate soft-deleted link
            $linkId = (int)$existing['id'];
            sqlStatement(
                'UPDATE `card_documents`
                 SET activity = 1, category_id = ?, created_at = NOW(), created_by = ?
                 WHERE id = ?',
                [$categoryId, $username, $linkId]
            );
            return $linkId;
        }

        try {
            sqlStatement(
                'INSERT INTO `card_documents`
                   (card_code, study_id, pid, document_id, category_id, created_by, created_at, activity)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)',
                [$cardCode, $studyId, $pid, $documentId, $categoryId, $username]
            );
            return (int)QueryUtils::lastInsertId();
        } catch (Throwable $e) {
            // Check for duplicate key error (1062)
            if (str_contains($e->getMessage(), '1062') || str_contains($e->getMessage(), 'Duplicate entry')) {
                throw new \InvalidArgumentException(xlt('This document is already attached to this study.'));
            }
            throw $e;
        }
    }

    /**
     * Verifies that the patient matches the active session and the user has permissions.
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    private static function verifySessionAndPermissions(string $cardCode, int $pid): void
    {
        $sessionPid = CardiologyContainer::getSessionPid();
        if ($pid <= 0 || $pid !== $sessionPid) {
            throw new \InvalidArgumentException(xlt('Invalid patient session. Operation rejected for security.'));
        }

        $userId   = (int)($_SESSION['authUserID'] ?? 0);
        $registry = new CardRegistry();
        if (!$registry->canWrite($cardCode, $userId)) {
            throw new \RuntimeException(xlt('Not authorized to modify this cardiology card.'));
        }

        if (!AclMain::aclCheckCore('patients', 'docs')) {
            throw new \RuntimeException(xlt('Not authorized to access patient documents.'));
        }
    }

    /**
     * Resolves and validates the table name of a study card.
     */
    private static function getStudyTableName(string $cardCode): ?string
    {
        $mod = sqlQuery(
            'SELECT table_name, card_type FROM `card_modules` WHERE code = ? LIMIT 1',
            [$cardCode]
        );
        if (!$mod || empty($mod['table_name'])) {
            return null;
        }

        if (isset($mod['card_type']) && $mod['card_type'] !== 'study') {
            return null;
        }

        $table = (string)$mod['table_name'];
        if (!preg_match('/^form_card_[a-z0-9_]+$/', $table)) {
            return null;
        }

        return $table;
    }

    /**
     * Converts a PHP ini size shorthand (e.g. '8M', '64M', '1G') to bytes.
     */
    private static function iniToBytes(string $val): int
    {
        $val = trim($val);
        if ($val === '') {
            return 0;
        }
        $last = strtolower($val[strlen($val) - 1]);
        $num  = (int)$val;
        switch ($last) {
            case 'g':
                $num *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $num *= 1024 * 1024;
                break;
            case 'k':
                $num *= 1024;
                break;
        }
        return $num;
    }

    /**
     * Maps standard PHP upload error codes to translated user-friendly messages.
     */
    private static function getUploadErrorMessage(int $code, string $filename): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return sprintf(xlt('File "%s" exceeds the maximum permitted file size.'), $filename);
            case UPLOAD_ERR_PARTIAL:
                return sprintf(xlt('File "%s" was only partially uploaded.'), $filename);
            case UPLOAD_ERR_NO_FILE:
                return xlt('No file was uploaded.');
            case UPLOAD_ERR_NO_TMP_DIR:
                return xlt('Missing temporary upload directory on server.');
            case UPLOAD_ERR_CANT_WRITE:
                return sprintf(xlt('Failed to write file "%s" to disk.'), $filename);
            case UPLOAD_ERR_EXTENSION:
                return sprintf(xlt('A server extension stopped the upload for file "%s".'), $filename);
            default:
                return sprintf(xlt('Unknown upload error for file "%s".'), $filename);
        }
    }
}

