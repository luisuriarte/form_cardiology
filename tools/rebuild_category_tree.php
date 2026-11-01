<?php

/**
 * rebuild_category_tree.php - Rebuild Document Categories Tree Tool
 *
 * Recomputes the nested-set preorder traversal values (lft, rght) for the entire
 * OpenEMR document categories hierarchy using the core CategoryTree / Tree routine.
 *
 * Only accessible to super administrators (admin/super).
 *
 * Compatible with OpenEMR 8.2.0 - 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

if (file_exists(__DIR__ . '/../../../globals.php')) {
    require_once(__DIR__ . '/../../../globals.php');
} elseif (file_exists(__DIR__ . '/../../../../globals.php')) {
    require_once(__DIR__ . '/../../../../globals.php');
}

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;

// Ensure only super administrators can run this maintenance tool.
if (!AclMain::aclCheckCore('admin', 'super')) {
    die(xlt('Not authorized. Only super administrators can rebuild the document categories tree.'));
}

$statusMessage = '';
$statusType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
        CsrfUtils::csrfNotAuth();
    }

    try {
        require_once $GLOBALS['srcdir'] . '/classes/Tree.class.php';
        require_once $GLOBALS['srcdir'] . '/classes/CategoryTree.class.php';

        // Root category in OpenEMR has ID 1 and left value 1.
        $tree = new \CategoryTree(1);
        $tree->rebuild_tree(1, 1);

        $statusType = 'success';
        $statusMessage = xlt('Document category tree successfully rebuilt. Preorder left and right boundaries (lft, rght) have been recomputed for all categories from parent links.');
    } catch (\Throwable $e) {
        $statusType = 'danger';
        $statusMessage = xlt('Error rebuilding document category tree: ') . ' ' . text($e->getMessage());
    }
}

Header::setupHeader(['bootstrap', 'fontawesome']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo xlt('Rebuild Document Categories Tree'); ?></title>
    <link rel="stylesheet" href="<?php echo attr($GLOBALS['webroot']); ?>/public/assets/bootstrap/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 25px 15px;
            font-family: inherit;
        }
        .container {
            max-width: 780px;
        }
        .card {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            background: #fff;
        }
        .card-header {
            background-color: #f1f5f9;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center">
            <i class="fa fa-sitemap me-2 text-primary" aria-hidden="true"></i>
            <span><?php echo xlt('Cardiology Maintenance: Rebuild Document Categories Tree'); ?></span>
        </div>
        <div class="card-body">
            <?php if (!empty($statusMessage)) : ?>
            <div class="alert alert-<?php echo attr($statusType); ?> alert-dismissible fade show" role="alert">
                <strong>
                    <?php echo $statusType === 'success' ? xlt('Success!') : xlt('Error!'); ?>
                </strong>
                <?php echo text($statusMessage); ?>
            </div>
            <?php endif; ?>

            <div class="alert alert-warning" role="alert">
                <h5 class="alert-heading">
                    <i class="fa fa-exclamation-triangle me-1"></i>
                    <?php echo xlt('Database Backup Recommended'); ?>
                </h5>
                <p class="mb-1">
                    <?php echo xlt('Before running this tool, please ensure you have taken a full database backup or backed up the following tables:'); ?>
                </p>
                <ul class="mb-0">
                    <li><code>categories</code></li>
                    <li><code>categories_seq</code></li>
                    <li><code>categories_to_documents</code></li>
                </ul>
            </div>

            <h5><?php echo xlt('What does this tool do?'); ?></h5>
            <p class="text-muted">
                <?php echo xlt('When custom modules or manual SQL migrations add document categories with initial placeholder boundaries (lft=0, rght=0), the nested-set tree structure can become inconsistent. This tool calls the official OpenEMR core routine'); ?>
                (<code>\CategoryTree::rebuild_tree(1, 1)</code>)
                <?php echo xlt('which traverses all categories starting from the root node and recomputes the preorder traversal indexes'); ?>
                (<code>lft</code> <?php echo xlt('and'); ?> <code>rght</code>)
                <?php echo xlt('strictly based on their parent-child relationships. It does NOT modify or delete category IDs, titles, parent links, or existing patient documents.'); ?>
            </p>

            <form method="post" action="rebuild_category_tree.php" onsubmit="return confirm(<?php echo xlj('Are you sure you want to rebuild the document categories tree? Please verify you have a backup before proceeding.'); ?>);">
                <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>">

                <div class="d-flex justify-content-between align-items-center mt-4">
                    <a href="javascript:history.back()" class="btn btn-outline-secondary">
                        <i class="fa fa-arrow-left me-1"></i> <?php echo xlt('Go Back'); ?>
                    </a>
                    <button type="submit" class="btn btn-danger">
                        <i class="fa fa-refresh me-1"></i> <?php echo xlt('Rebuild Document Categories Tree'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>

