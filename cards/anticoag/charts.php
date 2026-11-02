<?php

/**
 * Anticoagulation card – charts configuration.
 *
 * Defines longitudinal evolution metrics for anticoagulation studies.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Anticoag
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

return [
    'longitudinal' => [
        [
            'id' => 'inr',
            'title' => 'INR and target range',
            'unit' => '',
            'series' => [
                ['field' => 'inr_value', 'label' => 'INR', 'decimals' => 2],
                ['field' => 'target_inr_low', 'label' => 'Target low', 'decimals' => 2],
                ['field' => 'target_inr_high', 'label' => 'Target high', 'decimals' => 2],
            ],
        ],
        [
            'id' => 'dose',
            'title' => 'Weekly dose',
            'unit' => 'mg',
            'series' => [
                ['field' => 'current_weekly_dose_mg', 'label' => 'Current weekly dose', 'decimals' => 2],
                ['field' => 'new_weekly_dose_mg', 'label' => 'New weekly dose', 'decimals' => 2],
            ],
        ],
    ],
];
