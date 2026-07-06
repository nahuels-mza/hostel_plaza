<?php
/**
 * Lista de países para el formulario de reserva.
 * Fuente única para el <select> de nationality y los códigos de teléfono.
 *
 * Cada entrada: ['name' => string, 'code' => string, 'hint' => string]
 *   - code : prefijo telefónico internacional (vacío = desconocido)
 *   - hint : placeholder de ejemplo para el campo de teléfono local
 */
return [
    // América del Sur
    ['name' => 'Argentina',     'code' => '+54',  'hint' => '261 5990326'],
    ['name' => 'Bolivia',       'code' => '+591', 'hint' => '7 123 4567'],
    ['name' => 'Brazil',        'code' => '+55',  'hint' => '11 99999-9999'],
    ['name' => 'Chile',         'code' => '+56',  'hint' => '9 1234 5678'],
    ['name' => 'Colombia',      'code' => '+57',  'hint' => '300 123 4567'],
    ['name' => 'Ecuador',       'code' => '+593', 'hint' => '99 123 4567'],
    ['name' => 'Paraguay',      'code' => '+595', 'hint' => '981 123 456'],
    ['name' => 'Peru',          'code' => '+51',  'hint' => '999 123 456'],
    ['name' => 'Uruguay',       'code' => '+598', 'hint' => '94 123 456'],
    ['name' => 'Venezuela',     'code' => '+58',  'hint' => '412 123 4567'],

    // América del Norte y Central
    ['name' => 'Canada',        'code' => '+1',   'hint' => '416 555 0100'],
    ['name' => 'Mexico',        'code' => '+52',  'hint' => '55 1234 5678'],
    ['name' => 'United States', 'code' => '+1',   'hint' => '212 555 0100'],

    // Europa
    ['name' => 'Austria',       'code' => '+43',  'hint' => '664 123 4567'],
    ['name' => 'Belgium',       'code' => '+32',  'hint' => '470 12 34 56'],
    ['name' => 'Denmark',       'code' => '+45',  'hint' => '20 12 34 56'],
    ['name' => 'Finland',       'code' => '+358', 'hint' => '40 123 4567'],
    ['name' => 'France',        'code' => '+33',  'hint' => '6 12 34 56 78'],
    ['name' => 'Germany',       'code' => '+49',  'hint' => '170 1234567'],
    ['name' => 'Ireland',       'code' => '+353', 'hint' => '85 123 4567'],
    ['name' => 'Israel',        'code' => '+972', 'hint' => '50 123 4567'],
    ['name' => 'Italy',         'code' => '+39',  'hint' => '312 345 6789'],
    ['name' => 'Netherlands',   'code' => '+31',  'hint' => '6 1234 5678'],
    ['name' => 'Norway',        'code' => '+47',  'hint' => '400 12 345'],
    ['name' => 'Poland',        'code' => '+48',  'hint' => '512 345 678'],
    ['name' => 'Portugal',      'code' => '+351', 'hint' => '912 345 678'],
    ['name' => 'Spain',         'code' => '+34',  'hint' => '612 345 678'],
    ['name' => 'Sweden',        'code' => '+46',  'hint' => '70 123 45 67'],
    ['name' => 'Switzerland',   'code' => '+41',  'hint' => '78 123 45 67'],
    ['name' => 'United Kingdom','code' => '+44',  'hint' => '7911 123456'],

    // Oceanía
    ['name' => 'Australia',     'code' => '+61',  'hint' => '412 345 678'],
    ['name' => 'New Zealand',   'code' => '+64',  'hint' => '21 123 4567'],

    // Asia
    ['name' => 'China',         'code' => '+86',  'hint' => '131 1234 5678'],
    ['name' => 'India',         'code' => '+91',  'hint' => '98765 43210'],
    ['name' => 'Japan',         'code' => '+81',  'hint' => '90 1234 5678'],
    ['name' => 'South Korea',   'code' => '+82',  'hint' => '10 1234 5678'],

    // África
    ['name' => 'South Africa',  'code' => '+27',  'hint' => '71 123 4567'],

    // Otro
    ['name' => 'Other',         'code' => '',     'hint' => ''],
];
