<?php

/**
 * Holter Monitor card – charts configuration.
 *
 * Defines longitudinal evolution metrics for Holter studies.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Holter
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

return [
    'longitudinal' => [
        [
            'id' => 'hr',
            'title' => 'Heart rate',
            'unit' => 'bpm',
            'series' => [
                ['field' => 'hr_min_bpm', 'label' => 'Minimum heart rate', 'decimals' => 0],
                ['field' => 'hr_mean_bpm', 'label' => 'Mean heart rate', 'decimals' => 0],
                ['field' => 'hr_max_bpm', 'label' => 'Maximum heart rate', 'decimals' => 0],
            ],
        ],
        [
            'id' => 'pvc',
            'title' => 'PVC burden',
            'unit' => '%',
            'series' => [
                ['field' => 'pvc_burden_pct', 'label' => 'PVC burden', 'decimals' => 2],
            ],
        ],
        [
            'id' => 'af',
            'title' => 'Atrial fibrillation burden',
            'unit' => '%',
            'series' => [
                ['field' => 'af_burden_pct', 'label' => 'AF burden', 'decimals' => 2],
            ],
        ],
        [
            'id' => 'pauses',
            'title' => 'Longest pause',
            'unit' => 'ms',
            'series' => [
                ['field' => 'longest_pause_ms', 'label' => 'Longest pause', 'decimals' => 0],
            ],
        ],
    ],
];
