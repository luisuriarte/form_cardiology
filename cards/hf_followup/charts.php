<?php

/**
 * Heart Failure Follow-up card – charts configuration.
 *
 * Defines longitudinal evolution metrics for heart failure follow-up studies.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\HfFollowup
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

return [
    'longitudinal' => [
        [
            'id' => 'weight',
            'title' => 'Weight',
            'unit' => 'kg',
            'series' => [
                ['field' => 'weight_kg', 'label' => 'Current weight', 'decimals' => 2],
                ['field' => 'dry_weight_kg', 'label' => 'Dry weight', 'decimals' => 2],
            ],
        ],
        [
            'id' => 'nyha',
            'title' => 'NYHA functional class',
            'unit' => 'class',
            'y_min' => 1,
            'y_max' => 4,
            'series' => [
                ['field' => 'nyha_numeric', 'label' => 'NYHA class', 'decimals' => 0],
            ],
        ],
        [
            'id' => 'peptides',
            'title' => 'Natriuretic peptides',
            'unit' => 'pg/mL',
            'series' => [
                ['field' => 'nt_probnp_pg_ml', 'label' => 'NT-proBNP', 'decimals' => 2],
                ['field' => 'bnp_pg_ml', 'label' => 'BNP', 'decimals' => 2],
            ],
        ],
        [
            'id' => 'creatinine',
            'title' => 'Serum creatinine',
            'unit' => 'mg/dL',
            'series' => [
                ['field' => 'creatinine_mg_dl', 'label' => 'Creatinine', 'decimals' => 2],
            ],
        ],
        [
            'id' => 'potassium',
            'title' => 'Serum potassium',
            'unit' => 'mmol/L',
            'series' => [
                ['field' => 'potassium_mmol_l', 'label' => 'Potassium', 'decimals' => 2],
            ],
        ],
    ],
];
