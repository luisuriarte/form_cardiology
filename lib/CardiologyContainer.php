<?php

/**
 * CardiologyContainer – thin data-access layer for the form_cardiology table.
 *
 * Responsibilities:
 *   - ensureContainer()  : create the container row lazily (first save or
 *                          first child-card creation) and register it in the
 *                          OpenEMR `forms` table via addForm().
 *   - getContainer()     : fetch an active container row.
 *   - softDelete()       : set activity = 0 on the container row (and mirror
 *                          the deletion in the `forms` table via formDisappear()).
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Forms\Cardiology;

use OpenEMR\Common\Database\QueryUtils;

/**
 * Data-access helper for the form_cardiology (container) table.
 *
 * All public methods accept plain scalar arguments so they can be called
 * from procedural form scripts without instantiating a service layer.
 */
class CardiologyContainer
{
    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    /** Name registered in the forms table. Must match info.txt. */
    public const FORM_NAME = 'Cardiology';

    /** formdir registered in the forms table. Must match the folder name. */
    public const FORM_DIR = 'cardiology';

    /** Table that holds the container rows. */
    public const TABLE = 'form_cardiology';

    /**
     * Folder name of this module inside interface/forms/ (e.g. 'form_cardiology' or 'cardiology').
     */
    public static function getFormDir(): string
    {
        return basename(dirname(__DIR__));
    }

    /**
     * Root URL for scripts in this form (e.g. '/openemr/interface/forms/form_cardiology').
     */
    public static function getFormRootUrl(): string
    {
        return ($GLOBALS['rootdir'] ?? '') . '/forms/' . self::getFormDir();
    }

    /**
     * Web root URL for assets (e.g. '/openemr/interface/forms/form_cardiology').
     */
    public static function getWebRootUrl(): string
    {
        return ($GLOBALS['webroot'] ?? '') . '/interface/forms/' . self::getFormDir();
    }

    /**
     * Collect CSRF token compatible across OpenEMR versions (8.0, 8.2, 8.5+).
     */
    public static function collectCsrfToken(string $subject = 'default'): string
    {
        $session = $GLOBALS['session'] ?? \OpenEMR\Common\Session\SessionWrapperFactory::getInstance()->getActiveSession();
        try {
            $ref = new \ReflectionMethod(\OpenEMR\Common\Csrf\CsrfUtils::class, 'collectCsrfToken');
            $params = $ref->getParameters();
            if (!empty($params) && $params[0]->getName() === 'session') {
                return (string)\OpenEMR\Common\Csrf\CsrfUtils::collectCsrfToken($session, $subject);
            }
            return (string)\OpenEMR\Common\Csrf\CsrfUtils::collectCsrfToken($subject, $session);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Verify CSRF token compatible across OpenEMR versions.
     */
    public static function verifyCsrfToken(?string $token = null, string $subject = 'default'): bool
    {
        $session = $GLOBALS['session'] ?? \OpenEMR\Common\Session\SessionWrapperFactory::getInstance()->getActiveSession();
        $token = $token ?? $_POST['csrf_token_form'] ?? '';
        try {
            $ref = new \ReflectionMethod(\OpenEMR\Common\Csrf\CsrfUtils::class, 'verifyCsrfToken');
            $params = $ref->getParameters();
            if (count($params) >= 2 && $params[1]->getName() === 'session') {
                return (bool)\OpenEMR\Common\Csrf\CsrfUtils::verifyCsrfToken($token, $session, $subject);
            }
            return (bool)\OpenEMR\Common\Csrf\CsrfUtils::verifyCsrfToken($token, $subject, $session);
        } catch (\Throwable $e) {
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Return the active container row for this (pid, encounter), or null.
     *
     * @param  int $pid       Patient id.
     * @param  int $encounter Encounter number.
     * @return array<string,mixed>|null  Associative row array, or null when not found.
     */
    public static function getContainer(int $pid, int $encounter): ?array
    {
        $row = sqlQuery(
            'SELECT * FROM `form_cardiology`
              WHERE pid = ? AND encounter = ? AND activity = 1
              ORDER BY id ASC
              LIMIT 1',
            [$pid, $encounter]
        );

        return ($row && isset($row['id'])) ? $row : null;
    }

    /**
     * Return an active container row by its primary key, or null.
     *
     * @param  int $id Container primary key.
     * @return array<string,mixed>|null
     */
    public static function getContainerById(int $id): ?array
    {
        $row = sqlQuery(
            'SELECT * FROM `form_cardiology` WHERE id = ? AND activity = 1',
            [$id]
        );

        return ($row && isset($row['id'])) ? $row : null;
    }

    /**
     * Lazily create the container for (pid, encounter) if it does not exist.
     *
     * On first call for a given (pid, encounter) this method:
     *   1. Inserts a row into form_cardiology.
     *   2. Registers the form in the OpenEMR `forms` table via addForm().
     *
     * On subsequent calls it simply returns the existing container id.
     *
     * @param  int         $pid            Patient id.
     * @param  int         $encounter      Encounter number.
     * @param  string|null $reason         Optional reason text (stored on first create).
     * @param  string|null $notes          Optional notes text (stored on first create).
     * @param  int         $userauthorized Whether the current user is authorized (0 or 1).
     * @return int  The container id (form_cardiology.id).
     */
    public static function ensureContainer(
        int    $pid,
        int    $encounter,
        ?string $reason = null,
        ?string $notes = null,
        int    $userauthorized = 0
    ): int {
        $existing = self::getContainer($pid, $encounter);
        if ($existing !== null) {
            return (int)$existing['id'];
        }

        // Insert a new container row.
        $containerId = sqlInsert(
            'INSERT INTO `form_cardiology`
                (date, pid, encounter, `user`, groupname, authorized, activity, reason, notes)
             VALUES (NOW(), ?, ?, ?, ?, ?, 1, ?, ?)',
            [
                $pid,
                $encounter,
                $_SESSION['authUser']   ?? '',
                $_SESSION['authProvider'] ?? '',
                $userauthorized,
                $reason,
                $notes,
            ]
        );

        // Register the form in the OpenEMR forms table.
        // addForm() is provided by library/forms.inc.php which is included by
        // globals.php → it is always available in form scripts.
        addForm(
            $encounter,
            self::FORM_NAME,
            $containerId,
            self::getFormDir(),
            $pid,
            (string)$userauthorized
        );

        return (int)$containerId;
    }

    /**
     * Update the reason and notes fields of an existing container.
     *
     * @param  int         $containerId Primary key of the container.
     * @param  string|null $reason      New reason text (null = leave unchanged).
     * @param  string|null $notes       New notes text (null = leave unchanged).
     * @return void
     */
    public static function updateReasonNotes(
        int     $containerId,
        ?string $reason,
        ?string $notes
    ): void {
        sqlStatement(
            'UPDATE `form_cardiology` SET reason = ?, notes = ? WHERE id = ?',
            [$reason, $notes, $containerId]
        );
    }

    /**
     * Soft-delete the container (activity = 0).
     *
     * The OpenEMR framework marks the corresponding forms row via formDisappear()
     * (library/api.inc.php), which sets forms.deleted = 1. Child card rows are
     * NOT automatically deactivated by this method – see README.md for the
     * soft-delete cascade policy.
     *
     * @param  int $containerId Container primary key.
     * @param  int $pid         Patient id (required by formDisappear).
     * @return void
     */
    public static function softDelete(int $containerId, int $pid): void
    {
        // Deactivate the container row.
        sqlStatement(
            'UPDATE `form_cardiology` SET activity = 0 WHERE id = ? AND pid = ?',
            [$containerId, $pid]
        );

        // Mirror deletion in the OpenEMR forms table.
        // formDisappear() is provided by library/api.inc.php.
        formDisappear(self::TABLE, $containerId);
    }
}

