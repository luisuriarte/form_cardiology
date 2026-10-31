<?php

/**
 * CardRegistry – resolves which cardiology cards a user can see.
 *
 * Visibility rules (ALL must be true):
 *   1. card_modules.global_active = 1  (clinic-wide on/off switch)
 *   2. Effective per-user activation in card_config is active:
 *        - A row for the current user_id wins over the clinic default (user_id = 0).
 *        - If neither row exists, treat the card as active (default-on).
 *   3. AclMain::aclCheckCore(acl_section, acl_value) passes for the current user.
 *
 * Cards are ordered by card_modules.sort_order ASC.
 * Nothing is cached across requests.
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

use OpenEMR\Common\Acl\AclMain;

/**
 * Resolves and filters the list of cardiology card modules for a given user.
 */
class CardRegistry
{
    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    /**
     * Base path of the cards/ directory, relative to this file's directory.
     * Resolved to an absolute path in the constructor.
     */
    private string $cardsBasePath;

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    /**
     * @param string|null $cardsBasePath Absolute path to the cards/ directory.
     *                                   Defaults to <formdir>/cards/.
     */
    public function __construct(?string $cardsBasePath = null)
    {
        $this->cardsBasePath = $cardsBasePath
            ?? realpath(__DIR__ . '/../cards');
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Return the list of cards visible to the given user, in display order.
     *
     * Each element is an associative array with all card_modules columns plus:
     *   - 'installed'  bool  Whether the card folder and its required files exist.
     *   - 'user_active' bool Whether the effective activation for this user is on.
     *
     * Cards that fail ACL or global_active checks are omitted entirely.
     * Cards that fail the user-activation check are also omitted.
     * Cards whose folder is missing are included but marked installed = false.
     *
     * @param  int $userId  OpenEMR users.id of the current user.
     * @return list<array<string,mixed>>
     */
    public function getVisibleCards(int $userId): array
    {
        // 1. Load all globally-active modules, ordered for display.
        $result = sqlStatement(
            'SELECT * FROM `card_modules`
              WHERE global_active = 1
              ORDER BY sort_order ASC',
            []
        );

        // 2. Load all relevant card_config rows for this user and the clinic
        //    default (user_id = 0) in one query.
        $configMap = $this->loadConfigMap($userId);

        $visible = [];

        while ($row = sqlFetchArray($result)) {
            $code = (string)$row['code'];

            // --- Rule 2: per-user activation ---------------------------------
            if (!$this->isUserActive($code, $userId, $configMap)) {
                continue;
            }

            // --- Rule 3: ACL check -------------------------------------------
            $section = (string)($row['acl_section'] ?? 'patients');
            $value   = (string)($row['acl_value']   ?? 'med');
            if (!AclMain::aclCheckCore($section, $value)) {
                continue;
            }

            // --- Installation check (card folder + required files) -----------
            $row['installed'] = $this->isInstalled($code);

            $visible[] = $row;
        }

        return $visible;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Load card_config rows for $userId and the clinic default (user_id = 0),
     * indexed as  $map[$code][$userId] = (bool)active.
     *
     * @param  int $userId
     * @return array<string, array<int, bool>>
     */
    private function loadConfigMap(int $userId): array
    {
        $res = sqlStatement(
            'SELECT module_code, user_id, active
               FROM `card_config`
              WHERE user_id IN (0, ?)',
            [$userId]
        );

        $map = [];
        while ($row = sqlFetchArray($res)) {
            $code   = (string)$row['module_code'];
            $uid    = (int)$row['user_id'];
            $active = (bool)(int)$row['active'];
            $map[$code][$uid] = $active;
        }

        return $map;
    }

    /**
     * Resolve the effective activation for ($code, $userId) from the config map.
     *
     * Priority: user-specific row > clinic default row (user_id = 0) > true (default-on).
     *
     * @param  string                        $code
     * @param  int                           $userId
     * @param  array<string,array<int,bool>> $configMap
     * @return bool
     */
    private function isUserActive(string $code, int $userId, array $configMap): bool
    {
        if (isset($configMap[$code][$userId])) {
            // User-specific row wins.
            return $configMap[$code][$userId];
        }

        if (isset($configMap[$code][0])) {
            // Clinic default row.
            return $configMap[$code][0];
        }

        // No row at all → default active.
        return true;
    }

    /**
     * Check whether the card folder and its three required PHP files exist.
     *
     * Required files: new.php, view.php (or save.php as proxy), print.php.
     * A card is considered "installed" when new.php, save.php and print.php
     * all exist inside cards/<code>/.
     *
     * @param  string $code Card code (e.g. "echo", "stress_test").
     * @return bool
     */
    private function isInstalled(string $code): bool
    {
        $dir = $this->cardsBasePath . DIRECTORY_SEPARATOR . $code;

        if (!is_dir($dir)) {
            return false;
        }

        $required = ['new.php', 'save.php', 'print.php'];
        foreach ($required as $file) {
            if (!file_exists($dir . DIRECTORY_SEPARATOR . $file)) {
                return false;
            }
        }

        return true;
    }
}

