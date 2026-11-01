<?php

/**
 * ECG card – charts configuration.
 *
 * Defines longitudinal evolution metrics for 12-lead ECG studies.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\ECG
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

return [
    'longitudinal' => [
        [
            'id' => 'ecg_hr',
            'title' => 'Heart rate',
            'unit' => 'bpm',
            'series' => [
                ['field' => 'heart_rate_bpm', 'label' => 'Heart rate'],
            ],
        ],
        [
            'id' => 'ecg_qtc',
            'title' => 'Corrected QT interval',
            'unit' => 'ms',
            'series' => [
                ['field' => 'qtc_bazett_ms', 'label' => 'QTc Bazett'],
                ['field' => 'qtc_fridericia_ms', 'label' => 'QTc Fridericia'],
            ],
        ],
        [
            'id' => 'ecg_intervals',
            'title' => 'PR and QRS duration',
            'unit' => 'ms',
            'series' => [
                ['field' => 'pr_ms', 'label' => 'PR'],
                ['field' => 'qrs_ms', 'label' => 'QRS'],
            ],
        ],
    ],
];

