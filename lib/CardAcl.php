<?php

/**
 * CardAcl – Cardiology-specific permissions and ACL bootstrap helpers.
 *
 * Custom ACOs:
 *   cardiology/lists     manage the card_* list_options catalogs
 *   cardiology/settings  manage clinic-wide settings and card configuration
 *
 * OpenEMR administrators (admin/super) always pass both checks. The
 * administrator-only installation helper is intentionally kept separate from
 * the runtime checks so ACL installation can be triggered from the ACL tab.
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
use OpenEMR\Gacl\GaclApi;
use Throwable;

final class CardAcl
{
    public const ACO_SECTION = 'cardiology';
    public const ACO_LISTS = 'lists';
    public const ACO_SETTINGS = 'settings';

    /** Default ARO group names for each custom ACO. */
    private const DEFAULT_GROUPS = [
        self::ACO_LISTS => ['Administrators', 'Physicians', 'Clinicians'],
        self::ACO_SETTINGS => ['Administrators'],
    ];

    /**
     * Can the current user add, rename, reorder, activate or deactivate
     * options in the card_* list catalogs?
     */
    public static function canEditLists(): bool
    {
        return AclMain::aclCheckCore('admin', 'super')
            || AclMain::aclCheckCore('encounters', 'coding')
            || AclMain::aclCheckCore(self::ACO_SECTION, self::ACO_LISTS);
    }

    /**
     * Can the current user change clinic-wide settings and card configuration?
     */
    public static function canManageSettings(): bool
    {
        return AclMain::aclCheckCore('admin', 'super')
            || AclMain::aclCheckCore('encounters', 'coding')
            || AclMain::aclCheckCore(self::ACO_SECTION, self::ACO_SETTINGS);
    }

    /**
     * Can the current user store personal preferences?
     *
     * Personal preferences are scoped to the authenticated user and require
     * patients/med or encounters/coding permission.
     */
    public static function canUsePersonalSettings(): bool
    {
        return (int)($_SESSION['authUserID'] ?? 0) > 0
            && (AclMain::aclCheckCore('patients', 'med') || AclMain::aclCheckCore('encounters', 'coding'));
    }

    /**
     * Check that both custom ACO objects exist in phpGACL.
     */
    public static function isAclInstalled(): bool
    {
        try {
            $gacl = new GaclApi();
            return $gacl->get_object_id(self::ACO_SECTION, self::ACO_LISTS, 'ACO') !== false
                && $gacl->get_object_id(self::ACO_SECTION, self::ACO_SETTINGS, 'ACO') !== false;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Return the default group names for a custom ACO.
     *
     * @return list<string>
     */
    public static function defaultGroups(string $acoValue): array
    {
        return self::DEFAULT_GROUPS[$acoValue] ?? [];
    }

    /**
     * Return a small status snapshot for the ACL administration tab.
     *
     * @return array{
     *   installed:bool,
     *   acos:array<string,bool>,
     *   groups:array<string,array<string,bool>>
     * }
     */
    public static function status(): array
    {
        $status = [
            'installed' => false,
            'acos' => [],
            'groups' => [],
        ];

        try {
            $gacl = new GaclApi();
            foreach ([self::ACO_LISTS, self::ACO_SETTINGS] as $acoValue) {
                $exists = $gacl->get_object_id(self::ACO_SECTION, $acoValue, 'ACO') !== false;
                $status['acos'][$acoValue] = $exists;
                $status['groups'][$acoValue] = [];

                foreach (self::defaultGroups($acoValue) as $groupName) {
                    $groupId = $gacl->get_group_id(null, $groupName, 'ARO');
                    if ($groupId === false) {
                        $status['groups'][$acoValue][$groupName] = false;
                        continue;
                    }

                    $aclIds = $gacl->search_acl(
                        self::ACO_SECTION,
                        $acoValue,
                        null,
                        null,
                        $groupName,
                        null,
                        null,
                        null,
                        'write'
                    );
                    $status['groups'][$acoValue][$groupName] = is_array($aclIds) && count($aclIds) > 0;
                }
            }

            $status['installed'] = !empty($status['acos'][self::ACO_LISTS])
                && !empty($status['acos'][self::ACO_SETTINGS]);
        } catch (Throwable $e) {
            $status['installed'] = false;
        }

        return $status;
    }

    /**
     * Create the cardiology ACO section, both ACO objects, and the default
     * group grants. Safe to call repeatedly.
     *
     * @return array{
     *   created:list<string>,
     *   existing:list<string>,
     *   missing_groups:list<string>,
     *   failed:list<string>
     * }
     */
    public static function installDefaults(): array
    {
        $result = [
            'created' => [],
            'existing' => [],
            'missing_groups' => [],
            'failed' => [],
        ];

        $gacl = new GaclApi();

        $sectionId = $gacl->get_object_section_section_id(null, self::ACO_SECTION, 'ACO');
        if ($sectionId === false) {
            $createdSection = $gacl->add_object_section(
                'Cardiology',
                self::ACO_SECTION,
                10,
                0,
                'ACO'
            );
            if ($createdSection === false) {
                $result['failed'][] = 'section:' . self::ACO_SECTION;
                return $result;
            }
            $result['created'][] = 'section:' . self::ACO_SECTION;
        }

        $acoNames = [
            self::ACO_LISTS => 'Cardiology - Lists',
            self::ACO_SETTINGS => 'Cardiology - Settings',
        ];

        foreach ($acoNames as $acoValue => $acoName) {
            $objectId = $gacl->get_object_id(self::ACO_SECTION, $acoValue, 'ACO');
            if ($objectId === false) {
                $createdObject = $gacl->add_object(
                    self::ACO_SECTION,
                    $acoName,
                    $acoValue,
                    10,
                    0,
                    'ACO'
                );
                if ($createdObject === false) {
                    $result['failed'][] = 'aco:' . $acoValue;
                    continue;
                }
                $result['created'][] = 'aco:' . $acoValue;
            }

            foreach (self::defaultGroups($acoValue) as $groupName) {
                $groupId = $gacl->get_group_id(null, $groupName, 'ARO');
                if ($groupId === false) {
                    $result['missing_groups'][] = $groupName;
                    continue;
                }

                $aclIds = $gacl->search_acl(
                    self::ACO_SECTION,
                    $acoValue,
                    null,
                    null,
                    $groupName,
                    null,
                    null,
                    null,
                    'write'
                );

                if (is_array($aclIds) && count($aclIds) > 0) {
                    $result['existing'][] = $acoValue . ':' . $groupName;
                    continue;
                }

                $createdAcl = $gacl->add_acl(
                    [self::ACO_SECTION => [$acoValue]],
                    null,
                    [$groupId],
                    null,
                    null,
                    1,
                    1,
                    'write',
                    'Cardiology ' . $acoValue . ' access',
                    self::ACO_SECTION
                );

                if ($createdAcl === false) {
                    $result['failed'][] = 'acl:' . $acoValue . ':' . $groupName;
                } else {
                    $result['created'][] = 'acl:' . $acoValue . ':' . $groupName;
                }
            }
        }

        return $result;
    }
}
