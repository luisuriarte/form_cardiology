<?php

/**
 * CardAudit – thin wrapper around OpenEMR EventAuditLogger for the
 * Cardiology settings screens.
 *
 * Audit failures never break the user operation. Every successful or failed
 * settings action records the user, target list/setting/card, and old/new
 * values in the audit comment.
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

use OpenEMR\Common\Logging\EventAuditLogger;
use Throwable;

final class CardAudit
{
    private const MENU_ITEM = 'Cardiology Settings';
    private const MAX_COMMENT_LENGTH = 2000;

    /**
     * Write one audit event.
     *
     * @param string               $action  Short action code (list_add, setting_set, ...).
     * @param string               $target  List id, setting key, card code or ACL object.
     * @param array<string,scalar|null> $details Additional key/value context.
     */
    public static function log(
        string $action,
        string $target,
        array $details = [],
        bool $success = true
    ): void {
        try {
            $user = (string)($_SESSION['authUser'] ?? '');
            $group = (string)($_SESSION['authProvider'] ?? '');

            $parts = [
                'action=' . self::clean($action),
                'target=' . self::clean($target),
                'user=' . self::clean($user),
                'success=' . ($success ? '1' : '0'),
            ];

            foreach ($details as $key => $value) {
                if ($value === null) {
                    $value = '';
                } elseif (is_bool($value)) {
                    $value = $value ? '1' : '0';
                }

                $parts[] = self::clean((string)$key) . '=' . self::clean((string)$value);
            }

            $comment = 'Cardiology settings: ' . implode('; ', $parts);
            if (mb_strlen($comment, 'UTF-8') > self::MAX_COMMENT_LENGTH) {
                $comment = mb_substr($comment, 0, self::MAX_COMMENT_LENGTH - 3, 'UTF-8') . '...';
            }

            EventAuditLogger::getInstance()->newEvent(
                'update',
                $user,
                $group,
                $success ? 1 : 0,
                $comment,
                null,
                'open-emr',
                self::MENU_ITEM
            );
        } catch (Throwable $e) {
            // Auditing must not interrupt the settings operation.
        }
    }

    private static function clean(string $value): string
    {
        $value = str_replace(["\r", "\n", ';'], [' ', ' ', ','], $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return trim($value);
    }
}
