<?php

/**
 * Chagas Disease Follow-up card – charts configuration.
 *
 * Defines longitudinal evolution metrics for Chagas disease studies.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Chagas
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

return [
    'longitudinal' => [
        [
            'id' => 'chagas_lvef',
            'title' => 'Left ventricular ejection fraction',
            'unit' => '%',
            'series' => [
                ['field' => 'lvef_pct', 'label' => 'Left ventricular ejection fraction', 'decimals' => 1],
            ],
        ],
        [
            'id' => 'chagas_lvedd',
            'title' => 'Left ventricular end-diastolic diameter',
            'unit' => 'mm',
            'series' => [
                ['field' => 'lvedd_mm', 'label' => 'Left ventricular end-diastolic diameter', 'decimals' => 1],
            ],
        ],
    ],
];
