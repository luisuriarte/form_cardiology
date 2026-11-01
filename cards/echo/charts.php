<?php

/**
 * Echo card – charts configuration.
 *
 * Defines longitudinal evolution metrics for Doppler Echocardiogram studies.
 *
 * @package   OpenEMR
 * @subpackage Cardiology\Echo
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

return [
    'longitudinal' => [
        [
            'id' => 'lvef',
            'title' => 'Left ventricular ejection fraction',
            'unit' => '%',
            'series' => [
                ['field' => 'lvef_pct', 'label' => 'LVEF'],
            ],
        ],
        [
            'id' => 'lv_diameters',
            'title' => 'Left ventricular diameters',
            'unit' => 'mm',
            'series' => [
                ['field' => 'lvedd_mm', 'label' => 'LVEDD'],
                ['field' => 'lvesd_mm', 'label' => 'LVESD'],
            ],
        ],
        [
            'id' => 'pasp',
            'title' => 'Pulmonary artery systolic pressure',
            'unit' => 'mmHg',
            'series' => [
                ['field' => 'pasp_mmhg', 'label' => 'PASP'],
            ],
        ],
        [
            'id' => 'aorta',
            'title' => 'Aortic dimensions',
            'unit' => 'mm',
            'series' => [
                ['field' => 'ao_root_mm', 'label' => 'Aortic root'],
                ['field' => 'asc_aorta_mm', 'label' => 'Ascending aorta'],
            ],
        ],
    ],
];

