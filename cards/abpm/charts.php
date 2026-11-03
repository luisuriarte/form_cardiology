<?php

/**
 * Ambulatory Blood Pressure Monitoring card – charts configuration.
 *
 * Defines longitudinal evolution metrics for ABPM studies.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Abpm
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

return [
    'longitudinal' => [
        [
            'id' => 'abpm_24h',
            'title' => '24-hour blood pressure',
            'unit' => 'mmHg',
            'series' => [
                ['field' => 'sbp_24h', 'label' => '24-hour mean systolic blood pressure', 'decimals' => 0],
                ['field' => 'dbp_24h', 'label' => '24-hour mean diastolic blood pressure', 'decimals' => 0],
            ],
        ],
        [
            'id' => 'abpm_day_night',
            'title' => 'Daytime and nighttime blood pressure',
            'unit' => 'mmHg',
            'series' => [
                ['field' => 'sbp_day', 'label' => 'Daytime mean systolic blood pressure', 'decimals' => 0],
                ['field' => 'dbp_day', 'label' => 'Daytime mean diastolic blood pressure', 'decimals' => 0],
                ['field' => 'sbp_night', 'label' => 'Nighttime mean systolic blood pressure', 'decimals' => 0],
                ['field' => 'dbp_night', 'label' => 'Nighttime mean diastolic blood pressure', 'decimals' => 0],
            ],
        ],
        [
            'id' => 'abpm_dip',
            'title' => 'Nocturnal dip',
            'unit' => '%',
            'series' => [
                ['field' => 'dip_sbp_pct', 'label' => 'Systolic dip percentage', 'decimals' => 2],
                ['field' => 'dip_dbp_pct', 'label' => 'Diastolic dip percentage', 'decimals' => 2],
            ],
        ],
        [
            'id' => 'abpm_hr',
            'title' => 'Heart rate',
            'unit' => 'bpm',
            'series' => [
                ['field' => 'hr_24h', 'label' => '24-hour mean heart rate', 'decimals' => 0],
                ['field' => 'hr_day', 'label' => 'Daytime mean heart rate', 'decimals' => 0],
                ['field' => 'hr_night', 'label' => 'Nighttime mean heart rate', 'decimals' => 0],
            ],
        ],
    ],
];
