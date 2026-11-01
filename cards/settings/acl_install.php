<?php

/**
 * Cardiology Settings – ACL Installer action.
 *
 * Installs or repairs the custom Cardiology phpGACL ACO objects.
 * Only accessible to super administrators (admin/super).
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
use OpenEMR\Forms\Cardiology\CardiologyContainer;

require_once(__DIR__ . '/../../lib/CardiologyContainer.php');
require_once(__DIR__ . '/../../lib/CardAcl.php');

if (!AclMain::aclCheckCore('admin', 'super')) {
    die(xlt('Not authorized'));
}

$csrfToken = $_POST['csrf_token_form'] ?? '';
if (!CsrfUtils::verifyCsrfToken($csrfToken)) {
    CsrfUtils::csrfNotAuth();
}

$indexUrl = CardiologyContainer::getFormRootUrl() . '/cards/settings/index.php';

try {
    $res = CardAcl::installDefaults();
    $status = !empty($res['missing_groups']) ? 'partial' : 'ok';
    header('Location: ' . $indexUrl . '?tab=acl&acl=' . $status);
    exit;
} catch (\Throwable $e) {
    header('Location: ' . $indexUrl . '?tab=acl&acl=failed');
    exit;
}
