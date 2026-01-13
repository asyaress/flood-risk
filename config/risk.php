<?php

return [
    // Normalisasi sederhana untuk dummy
    'bmkg_rain_mm_per_hour' => [
        ['max' => 5,  'score' => 0.1],
        ['max' => 10, 'score' => 0.3],
        ['max' => 20, 'score' => 0.6],
        ['max' => INF,'score' => 1.0],
    ],

    // Sea level (cm) – dummy threshold
    'sea_level_cm' => [
        ['max' => 30, 'score' => 0.1],
        ['max' => 60, 'score' => 0.4],
        ['max' => 90, 'score' => 0.7],
        ['max' => INF,'score' => 1.0],
    ],

    // InaRISK dummy: 0..100 -> 0..1
    'inarisk_scale_max' => 100,

    // Level dari risk index
    'risk_levels' => [
        ['max' => 0.33, 'level' => 'LOW'],
        ['max' => 0.66, 'level' => 'MEDIUM'],
        ['max' => 1.00, 'level' => 'HIGH'],
    ],
];
