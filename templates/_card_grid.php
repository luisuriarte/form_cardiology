<?php
/**
 * Template partial: card grid.
 *
 * Renders one tile per visible card.
 *
 * Expects (from the including scope):
 *   $cards        list<array>  – from CardRegistry::getVisibleCards()
 *   $containerId  int          – 0 when no container exists yet (new.php)
 *   $studyCounts  array        – keyed by card code
 *   $studies      array        – keyed by card code → list of study rows
 *   $pid          int
 *   $encounter    int
 *
 * @package   OpenEMR
 * @subpackage Cardiology
 */

// Provide safe defaults when called from new.php (no container yet).
$containerId  = isset($containerId)  ? (int)$containerId  : 0;
$studyCounts  = isset($studyCounts)  ? $studyCounts        : [];
$studies      = isset($studies)      ? $studies            : [];
?>
<div class="row mb-3">
    <div class="col-12">
        <h5 class="cardiology-section-title">
            <?php echo xlt('Clinical Studies'); ?>
        </h5>
    </div>
</div>

<?php if (empty($cards)) : ?>
<div class="row">
    <div class="col-12">
        <div class="alert alert-info">
            <?php echo xlt('No cardiology modules are currently active for your account.'); ?>
        </div>
    </div>
</div>
<?php else : ?>

<div class="row cardiology-card-grid">
    <?php foreach ($cards as $card) :
        $code        = (string)$card['code'];
        $title       = xl((string)$card['title']);
        $icon        = (string)($card['icon'] ?? 'fa-stethoscope');
        $installed   = (bool)$card['installed'];
        $cardType    = (string)($card['card_type'] ?? 'study');
        $isTool      = ($cardType === 'tool');
        $count       = (int)($studyCounts[$code] ?? 0);
        $cardStudies = $studies[$code] ?? [];

        // Base URL fragment for child-card links.
        $cardBase = \OpenEMR\Forms\Cardiology\CardiologyContainer::getFormRootUrl() . '/cards/' . attr_url($code);

        // Query-string common to all child-card links.
        $qsBase = 'pid=' . attr_url((string)$pid)
            . '&encounter=' . attr_url((string)$encounter)
            . '&cardiology_id=' . attr_url((string)$containerId);

        $tileClass = 'cardiology-card-tile card h-100';
        if (!$installed) {
            $tileClass .= ' cardiology-card-disabled';
        }
    ?>
    <div class="col-12 col-sm-6 col-lg-4 mb-4">
        <div class="<?php echo attr($tileClass); ?>"
             <?php if (!$installed) : ?>
             title="<?php echo xla('Not installed'); ?>"
             data-toggle="tooltip"
             <?php endif; ?>>

            <!-- Card header -->
            <div class="card-header cardiology-card-header d-flex align-items-center justify-content-between">
                <span>
                    <i class="fa <?php echo attr($icon); ?> me-2" aria-hidden="true"></i>
                    <strong><?php echo text($title); ?></strong>
                </span>
                <?php if ($isTool && $installed) : ?>
                <a href="<?php echo attr($cardBase . '/index.php'); ?>"
                   class="btn btn-sm btn-outline-primary cardiology-btn-tool"
                   data-cardiology-tool-url="<?php echo attr($cardBase . '/index.php'); ?>"
                   data-cardiology-tool-title="<?php echo attr($title); ?>"
                   onclick="top.restoreSession()">
                    <i class="fa fa-arrow-right" aria-hidden="true"></i>
                    <?php echo xlt('Open'); ?>
                </a>
                <?php elseif (!$isTool && $installed && $containerId > 0) : ?>
                <a href="<?php echo attr($cardBase . '/new.php?' . $qsBase); ?>"
                   onclick="top.restoreSession()"
                   class="btn btn-sm btn-outline-primary cardiology-btn-new">
                    <i class="fa fa-plus" aria-hidden="true"></i>
                    <?php echo xlt('New'); ?>
                </a>
                <?php elseif (!$isTool && $installed && $containerId === 0) : ?>
                <button type="submit"
                        class="btn btn-sm btn-outline-primary cardiology-btn-new"
                        form="cardiology-container-form"
                        name="new_card_code"
                        value="<?php echo attr($code); ?>"
                        title="<?php echo xla('Save visit first, then open this card'); ?>">
                    <i class="fa fa-plus" aria-hidden="true"></i>
                    <?php echo xlt('New'); ?>
                </button>
                <?php endif; ?>
            </div>

            <!-- Card body: study count + study list -->
            <div class="card-body cardiology-card-body">
                <?php if (!$installed) : ?>
                <p class="text-muted fst-italic small mb-0">
                    <?php echo xlt('Not installed'); ?>
                </p>
                <?php elseif ($isTool) : ?>
                <p class="text-muted small mb-0">
                    <?php echo xlt('Manage list catalogs, card activation and preferences.'); ?>
                </p>
                <?php elseif ($count === 0) : ?>
                <p class="text-muted small mb-0">
                    <?php echo xlt('No studies for this encounter.'); ?>
                </p>
                <?php else : ?>
                <p class="small text-muted mb-2">
                    <?php echo sprintf(
                        xlt('%d study(s) in this encounter'),
                        $count
                    ); ?>
                </p>
                <ul class="list-unstyled cardiology-study-list mb-0">
                    <?php foreach ($cardStudies as $study) :
                        $studyId     = (int)$study['id'];
                        $studyDate   = text($study['study_date'] ?? '');
                        $studyStatus = (string)($study['status'] ?? 'draft');
                        $isFinal     = ($studyStatus === 'final');

                        $qsStudy = $qsBase . '&id=' . attr_url((string)$studyId);
                    ?>
                    <li class="cardiology-study-item d-flex align-items-center justify-content-between mb-1">
                        <span class="cardiology-study-date small">
                            <?php echo $studyDate; ?>
                        </span>
                        <!-- Status badge -->
                        <span class="badge <?php echo $isFinal ? 'bg-success' : 'bg-warning text-dark'; ?> cardiology-badge">
                            <?php echo $isFinal ? xlt('Final') : xlt('Draft'); ?>
                        </span>
                        <!-- Action buttons -->
                        <span class="cardiology-study-actions">
                            <?php if (!$isFinal) : ?>
                            <a href="<?php echo attr($cardBase . '/view.php?' . $qsStudy); ?>"
                               onclick="top.restoreSession()"
                               class="btn btn-xs btn-outline-secondary"
                               title="<?php echo xla('Open study'); ?>">
                                <i class="fa fa-pencil" aria-hidden="true"></i>
                            </a>
                            <?php else : ?>
                            <a href="<?php echo attr($cardBase . '/view.php?' . $qsStudy); ?>"
                               onclick="top.restoreSession()"
                               class="btn btn-xs btn-outline-secondary"
                               title="<?php echo xla('View study'); ?>">
                                <i class="fa fa-eye" aria-hidden="true"></i>
                            </a>
                            <?php endif; ?>
                            <a href="<?php echo attr($cardBase . '/print.php?' . $qsStudy); ?>"
                               onclick="top.restoreSession()"
                               class="btn btn-xs btn-outline-info"
                               target="_blank"
                               title="<?php echo xla('Print PDF'); ?>">
                                <i class="fa fa-print" aria-hidden="true"></i>
                            </a>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div><!-- .card-body -->

        </div><!-- .cardiology-card-tile -->
    </div><!-- .col -->
    <?php endforeach; ?>
</div><!-- .cardiology-card-grid -->

<?php endif; ?>

