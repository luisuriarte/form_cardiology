<?php

/**
 * Cardiology container – save.php
 *
 * Handles POST from new.php / view.php:
 *   - Verifies CSRF token.
 *   - Verifies ACL.
 *   - Calls CardiologyContainer::ensureContainer() to create the row if needed.
 *   - Updates reason and notes on an existing container.
 *   - Redirects (formJump) back to view.php so the user sees the updated form.
 *
 * Compatible with OpenEMR 8.2.0 – 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once('../../globals.php');
require_once($GLOBALS['srcdir'] . '/api.inc.php');
require_once($GLOBALS['srcdir'] . '/forms.inc.php');

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Forms\Cardiology\CardiologyContainer;
use OpenEMR\Forms\Cardiology\CardRegistry;

require_once(__DIR__ . '/lib/CardiologyContainer.php');
require_once(__DIR__ . '/lib/CardRegistry.php');

// ---------------------------------------------------------------------------
// CSRF verification (must be first, before reading any POST data)
// ---------------------------------------------------------------------------
if (!CardiologyContainer::verifyCsrfToken()) {
    CsrfUtils::csrfNotVerified();
}

// ---------------------------------------------------------------------------
// ACL check – require patients / med write
// ---------------------------------------------------------------------------
if (!AclMain::aclCheckCore('patients', 'med', '', 'write')) {
    die(xlt('Not authorized'));
}

// ---------------------------------------------------------------------------
// Collect and sanitise POST values
// ---------------------------------------------------------------------------
/** @var int $pid */
/** @var int $encounter */
/** @var int $userauthorized */

// pid and encounter come from globals.php (session-backed).
// We also accept them as POST hidden fields and cross-check.
$postPid       = (int)($_POST['pid']          ?? $pid);
$postEncounter = (int)($_POST['encounter']    ?? $encounter);
$cardiologyId  = (int)($_POST['cardiology_id'] ?? 0);

// Use session values as the authoritative source; POST values are checked
// for consistency but session values win (prevents cross-encounter tampering).
if ($pid !== $postPid || $encounter !== $postEncounter) {
    die(xlt('Session mismatch. Please reload and try again.'));
}

$reason = trim($_POST['reason'] ?? '');
$notes  = trim($_POST['notes']  ?? '');

// ---------------------------------------------------------------------------
// Create or update the container
// ---------------------------------------------------------------------------
if ($cardiologyId > 0) {
    // Container already exists – just update reason / notes.
    CardiologyContainer::updateReasonNotes(
        $cardiologyId,
        $reason !== '' ? $reason : null,
        $notes  !== '' ? $notes  : null
    );
    $containerId = $cardiologyId;
} else {
    // First save – create the container lazily.
    $containerId = CardiologyContainer::ensureContainer(
        (int)$pid,
        (int)$encounter,
        $reason !== '' ? $reason : null,
        $notes  !== '' ? $notes  : null,
        (int)$userauthorized
    );
}

// ---------------------------------------------------------------------------
// Optional "New" button from a study card tile: open that card directly.
// Only registered, globally active study cards the user may write are allowed.
// ---------------------------------------------------------------------------
$newCardCode = trim((string)($_POST['new_card_code'] ?? ''));
if ($newCardCode !== '') {
    if (!preg_match('/^[a-z0-9_]+$/', $newCardCode)) {
        formJump(CardiologyContainer::getFormRootUrl() . '/view.php?id=' . urlencode((string)$containerId));
        exit;
    }

    $newCardModule = sqlQuery(
        "SELECT code, table_name FROM `card_modules`
          WHERE code = ? AND card_type = 'study' AND global_active = 1
          LIMIT 1",
        [$newCardCode]
    );

    $userId = (int)($_SESSION['authUserID'] ?? 0);
    $registry = new CardRegistry();
    $tableName = (string)($newCardModule['table_name'] ?? '');
    if (
        $newCardModule
        && preg_match('/^form_card_[a-z0-9_]+$/', $tableName)
        && $registry->canWrite($newCardCode, $userId)
    ) {
        $newUrl = CardiologyContainer::getFormRootUrl()
            . '/cards/' . urlencode($newCardCode) . '/new.php?pid=' . urlencode((string)$pid)
            . '&encounter=' . urlencode((string)$encounter)
            . '&cardiology_id=' . urlencode((string)$containerId);
        formJump($newUrl);
        exit;
    }
}

// ---------------------------------------------------------------------------
// Redirect to view.php so the user sees the saved data and the card grid
// ---------------------------------------------------------------------------
$viewUrl = CardiologyContainer::getFormRootUrl()
    . '/view.php?id='
    . urlencode((string)$containerId);

formJump($viewUrl);

