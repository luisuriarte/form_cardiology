<?php

/**
 * Cardiology Settings – Backend API Endpoint.
 *
 * Handles AJAX and standard POST submissions for:
 *   - save_cards: card order and global/clinic/personal activation
 *   - save_settings: clinic-wide and personal preferences
 *   - list_add, list_rename, list_toggle, list_default, list_move: list options management
 *
 * Compatible with OpenEMR 8.2.0 - 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Settings
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

if (file_exists(__DIR__ . '/../../../../globals.php')) {
    require_once(__DIR__ . '/../../../../globals.php');
} elseif (file_exists(__DIR__ . '/../../../../../globals.php')) {
    require_once(__DIR__ . '/../../../../../globals.php');
}

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Forms\Cardiology\CardAcl;
use OpenEMR\Forms\Cardiology\CardAudit;
use OpenEMR\Forms\Cardiology\CardInput;
use OpenEMR\Forms\Cardiology\CardLists;
use OpenEMR\Forms\Cardiology\CardSettings;
use OpenEMR\Forms\Cardiology\CardiologyContainer;

require_once(__DIR__ . '/../../lib/CardiologyContainer.php');
require_once(__DIR__ . '/../../lib/CardAcl.php');
require_once(__DIR__ . '/../../lib/CardSettings.php');
require_once(__DIR__ . '/../../lib/CardLists.php');
require_once(__DIR__ . '/../../lib/CardAudit.php');
require_once(__DIR__ . '/../../lib/CardInput.php');

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        http_response_code(405);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => xlt('Method not allowed')]);
        exit;
    }
    die(xlt('Method not allowed'));
}

$csrfToken = $_POST['csrf_token_form'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!CsrfUtils::verifyCsrfToken($csrfToken)) {
    if ($isAjax) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => xlt('Security token expired or invalid (CSRF failure).')]);
        exit;
    }
    CsrfUtils::csrfNotAuth();
}

$userId = (int)($_SESSION['authUserID'] ?? 0);
$action = CardInput::text($_POST['action'] ?? '') ?? '';
$indexUrl = CardiologyContainer::getFormRootUrl() . '/cards/settings/index.php';

try {
    switch ($action) {
        case 'save_cards':
            if (!CardAcl::canManageSettings() && !CardAcl::canUsePersonalSettings()) {
                throw new \RuntimeException(xlt('Not authorized'));
            }

            $canManage = CardAcl::canManageSettings();
            $modulesRes = sqlStatement('SELECT code, card_type FROM `card_modules`', []);
            $allModules = [];
            while ($mRow = sqlFetchArray($modulesRes)) {
                $allModules[] = (string)$mRow['code'];
            }

            CardiologyContainer::startTransaction();
            try {
                if ($canManage) {
                    $globalActive = $_POST['global_active'] ?? [];
                    $sortOrders   = $_POST['sort_order'] ?? [];
                    $clinicActive = $_POST['clinic_active'] ?? [];

                    foreach ($allModules as $code) {
                        // settings tool card is always globally active
                        $isGlobal = ($code === 'settings') ? 1 : (!empty($globalActive[$code]) ? 1 : 0);
                        $sort = isset($sortOrders[$code]) ? (int)$sortOrders[$code] : 100;
                        if ($sort < 0) $sort = 0;
                        if ($sort > 9999) $sort = 9999;

                        sqlStatement(
                            'UPDATE `card_modules` SET `global_active` = ?, `sort_order` = ? WHERE `code` = ?',
                            [$isGlobal, $sort, $code]
                        );

                        $isClinic = !empty($clinicActive[$code]) ? 1 : 0;
                        sqlStatement(
                            'INSERT INTO `card_config` (`module_code`, `user_id`, `active`) VALUES (?, 0, ?)
                             ON DUPLICATE KEY UPDATE `active` = VALUES(`active`)',
                            [$code, $isClinic]
                        );
                    }
                }

                if (CardAcl::canUsePersonalSettings() && $userId > 0) {
                    $personalActive = $_POST['personal_active'] ?? [];
                    foreach ($allModules as $code) {
                        $isPersonal = !empty($personalActive[$code]) ? 1 : 0;
                        sqlStatement(
                            'INSERT INTO `card_config` (`module_code`, `user_id`, `active`) VALUES (?, ?, ?)
                             ON DUPLICATE KEY UPDATE `active` = VALUES(`active`)',
                            [$code, $userId, $isPersonal]
                        );
                    }
                }

                CardiologyContainer::commitTransaction();
                CardAudit::log('save_cards', 'card_modules', ['user_id' => $userId]);

                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => true, 'message' => xlt('Card configuration saved successfully.')]);
                    exit;
                }
                header('Location: ' . $indexUrl . '?tab=cards&msg=cards_saved');
                exit;
            } catch (\Throwable $e) {
                CardiologyContainer::rollbackTransaction();
                throw $e;
            }

        case 'save_settings':
            $scope = CardInput::text($_POST['scope'] ?? 'clinic') ?? 'clinic';
            $settingsData = $_POST['settings'] ?? [];
            if (!is_array($settingsData)) {
                $settingsData = [];
            }

            if ($scope === 'clinic') {
                if (!CardAcl::canManageSettings()) {
                    throw new \RuntimeException(xlt('Not authorized'));
                }
                foreach ($settingsData as $key => $val) {
                    CardSettings::set((string)$key, (string)$val, CardSettings::SCOPE_CLINIC, 0);
                }
            } else {
                if (!CardAcl::canUsePersonalSettings()) {
                    throw new \RuntimeException(xlt('Not authorized'));
                }
                foreach ($settingsData as $key => $val) {
                    CardSettings::set((string)$key, (string)$val, CardSettings::SCOPE_USER, $userId);
                }
            }

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'message' => xlt('Settings saved successfully.')]);
                exit;
            }
            header('Location: ' . $indexUrl . '?tab=preferences&msg=settings_saved');
            exit;

        case 'list_add':
            if (!CardAcl::canEditLists()) {
                throw new \RuntimeException(xlt('Not authorized'));
            }
            $listId = CardInput::text($_POST['list_id'] ?? '') ?? '';
            $title  = CardInput::text($_POST['title'] ?? '') ?? '';
            if ($listId === '' || $title === '') {
                throw new \InvalidArgumentException(xlt('List ID and option title are required.'));
            }

            CardLists::assertRegisteredList($listId);
            $optionId = CardLists::generateOptionId($listId, $title);
            $nextSeq  = CardLists::nextSeq($listId);

            sqlStatement(
                'INSERT INTO `list_options` (`list_id`, `option_id`, `title`, `seq`, `is_default`, `activity`)
                 VALUES (?, ?, ?, ?, 0, 1)',
                [$listId, $optionId, $title, $nextSeq]
            );

            CardAudit::log('list_add', $listId, ['option_id' => $optionId, 'title' => $title]);

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'message' => xlt('List option added.'), 'option_id' => $optionId]);
                exit;
            }
            header('Location: ' . $indexUrl . '?tab=lists&list=' . urlencode($listId) . '&msg=list_added');
            exit;

        case 'list_rename':
            if (!CardAcl::canEditLists()) {
                throw new \RuntimeException(xlt('Not authorized'));
            }
            $listId   = CardInput::text($_POST['list_id'] ?? '') ?? '';
            $optionId = CardInput::text($_POST['option_id'] ?? '') ?? '';
            $title    = CardInput::text($_POST['title'] ?? '') ?? '';
            if ($listId === '' || $optionId === '' || $title === '') {
                throw new \InvalidArgumentException(xlt('Missing required parameters.'));
            }

            CardLists::assertRegisteredList($listId);
            sqlStatement(
                'UPDATE `list_options` SET `title` = ? WHERE `list_id` = ? AND `option_id` = ?',
                [$title, $listId, $optionId]
            );

            CardAudit::log('list_rename', $listId, ['option_id' => $optionId, 'title' => $title]);

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'message' => xlt('List option renamed.')]);
                exit;
            }
            header('Location: ' . $indexUrl . '?tab=lists&list=' . urlencode($listId) . '&msg=list_renamed');
            exit;

        case 'list_toggle':
            if (!CardAcl::canEditLists()) {
                throw new \RuntimeException(xlt('Not authorized'));
            }
            $listId   = CardInput::text($_POST['list_id'] ?? '') ?? '';
            $optionId = CardInput::text($_POST['option_id'] ?? '') ?? '';
            $active   = CardInput::int($_POST['active'] ?? null, 'active', 0, 1) ?? 1;
            if ($listId === '' || $optionId === '') {
                throw new \InvalidArgumentException(xlt('Missing required parameters.'));
            }

            CardLists::assertRegisteredList($listId);
            sqlStatement(
                'UPDATE `list_options` SET `activity` = ? WHERE `list_id` = ? AND `option_id` = ?',
                [$active, $listId, $optionId]
            );

            CardAudit::log('list_toggle', $listId, ['option_id' => $optionId, 'active' => $active]);

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'message' => xlt('List option updated.')]);
                exit;
            }
            header('Location: ' . $indexUrl . '?tab=lists&list=' . urlencode($listId) . '&msg=list_updated');
            exit;

        case 'list_default':
            if (!CardAcl::canEditLists()) {
                throw new \RuntimeException(xlt('Not authorized'));
            }
            $listId   = CardInput::text($_POST['list_id'] ?? '') ?? '';
            $optionId = CardInput::text($_POST['option_id'] ?? '') ?? '';
            if ($listId === '' || $optionId === '') {
                throw new \InvalidArgumentException(xlt('Missing required parameters.'));
            }

            CardLists::assertRegisteredList($listId);
            CardiologyContainer::startTransaction();
            try {
                sqlStatement('UPDATE `list_options` SET `is_default` = 0 WHERE `list_id` = ?', [$listId]);
                sqlStatement('UPDATE `list_options` SET `is_default` = 1 WHERE `list_id` = ? AND `option_id` = ?', [$listId, $optionId]);
                CardiologyContainer::commitTransaction();
                CardAudit::log('list_default', $listId, ['option_id' => $optionId]);

                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => true, 'message' => xlt('Default option updated.')]);
                    exit;
                }
                header('Location: ' . $indexUrl . '?tab=lists&list=' . urlencode($listId) . '&msg=list_default');
                exit;
            } catch (\Throwable $e) {
                CardiologyContainer::rollbackTransaction();
                throw $e;
            }

        case 'list_move':
            if (!CardAcl::canEditLists()) {
                throw new \RuntimeException(xlt('Not authorized'));
            }
            $listId    = CardInput::text($_POST['list_id'] ?? '') ?? '';
            $optionId  = CardInput::text($_POST['option_id'] ?? '') ?? '';
            $direction = CardInput::text($_POST['direction'] ?? '') ?? 'up';
            if ($listId === '' || $optionId === '') {
                throw new \InvalidArgumentException(xlt('Missing required parameters.'));
            }

            CardLists::assertRegisteredList($listId);
            $allOptions = CardLists::options($listId);
            $currentIndex = -1;
            foreach ($allOptions as $idx => $opt) {
                if ((string)$opt['option_id'] === $optionId) {
                    $currentIndex = $idx;
                    break;
                }
            }

            if ($currentIndex !== -1) {
                $targetIndex = ($direction === 'up') ? $currentIndex - 1 : $currentIndex + 1;
                if ($targetIndex >= 0 && $targetIndex < count($allOptions)) {
                    $currOpt   = $allOptions[$currentIndex];
                    $targetOpt = $allOptions[$targetIndex];

                    CardiologyContainer::startTransaction();
                    try {
                        sqlStatement('UPDATE `list_options` SET `seq` = ? WHERE `list_id` = ? AND `option_id` = ?', [(int)$targetOpt['seq'], $listId, $currOpt['option_id']]);
                        sqlStatement('UPDATE `list_options` SET `seq` = ? WHERE `list_id` = ? AND `option_id` = ?', [(int)$currOpt['seq'], $listId, $targetOpt['option_id']]);
                        CardiologyContainer::commitTransaction();
                        CardAudit::log('list_move', $listId, ['option_id' => $optionId, 'direction' => $direction]);
                    } catch (\Throwable $e) {
                        CardiologyContainer::rollbackTransaction();
                        throw $e;
                    }
                }
            }

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'message' => xlt('List option moved.')]);
                exit;
            }
            header('Location: ' . $indexUrl . '?tab=lists&list=' . urlencode($listId) . '&msg=list_moved');
            exit;

        default:
            throw new \InvalidArgumentException(xlt('Unknown action'));
    }
} catch (\Throwable $e) {
    if ($isAjax) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
    die(text($e->getMessage()));
}

