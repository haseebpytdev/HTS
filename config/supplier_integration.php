<?php

/**
 * Backward-compatible aggregate config. Prefer:
 * - config('integrations') for driver / buffers
 * - config('travelport'|'sabre'|'amadeus'|'iati'|'duffel') for vendor settings
 */
return array_merge(
    require __DIR__.'/integrations.php',
    [
        'travelport' => require __DIR__.'/travelport.php',
        'sabre' => require __DIR__.'/sabre.php',
        'amadeus' => require __DIR__.'/amadeus.php',
        'iati' => require __DIR__.'/iati.php',
        'duffel' => require __DIR__.'/duffel.php',
    ]
);
