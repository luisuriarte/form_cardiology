<?php

/**
 * Stress Test card – charts configuration.
 *
 * Defines longitudinal evolution metrics and within-study exercise stage trends.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\StressTest
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

return [
    'longitudinal' => [
        [
            'id' => 'hr',
            'title' => 'Heart rate response',
            'unit' => 'bpm',
            'series' => [
                ['field' => 'peak_hr_bpm', 'label' => 'Peak HR'],
                ['field' => 'target_hr_bpm', 'label' => 'Target HR'],
            ],
        ],
        [
            'id' => 'mets',
            'title' => 'Functional capacity',
            'unit' => 'METs',
            'series' => [
                ['field' => 'mets_achieved', 'label' => 'METs'],
            ],
        ],
        [
            'id' => 'duration',
            'title' => 'Exercise duration',
            'unit' => 'min',
            'series' => [
                ['field' => 'exercise_duration_s', 'label' => 'Exercise duration', 'scale' => 1 / 60, 'decimals' => 1],
            ],
        ],
        [
            'id' => 'duke',
            'title' => 'Duke treadmill score',
            'unit' => 'points',
            'series' => [
                ['field' => 'duke_score', 'label' => 'Duke score'],
            ],
        ],
    ],
    'study' => [
        'stage_table' => 'form_card_stress_test_stage',
        'foreign_key' => 'stress_test_id',
        'x_field' => 'elapsed_s',
        'charts' => [
            [
                'id' => 'stage_hr',
                'title' => 'Heart rate during the test',
                'unit' => 'bpm',
                'series' => [
                    ['field' => 'hr_bpm', 'label' => 'HR'],
                ],
            ],
            [
                'id' => 'stage_bp',
                'title' => 'Blood pressure during the test',
                'unit' => 'mmHg',
                'series' => [
                    ['field' => 'bp_systolic_mmhg', 'label' => 'Systolic'],
                    ['field' => 'bp_diastolic_mmhg', 'label' => 'Diastolic'],
                ],
            ],
            [
                'id' => 'stage_st',
                'title' => 'ST segment level during the test',
                'unit' => 'mm',
                'series' => [
                    ['field' => 'st_mm', 'label' => 'ST segment'],
                ],
            ],
        ],
    ],
];

