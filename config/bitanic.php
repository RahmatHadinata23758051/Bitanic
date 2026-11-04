<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Heartbeat Availability Timeout (Seconds)
    |--------------------------------------------------------------------------
    |
    | Defines the timeout window after which a device without an authenticated
    | heartbeat or hardware telemetry transmission is considered offline.
    | Default proposal: 900 seconds (15 minutes).
    |
    */
    'heartbeat_timeout_seconds' => (int) env('BITANIC_HEARTBEAT_TIMEOUT_SECONDS', 900),

    /*
    |--------------------------------------------------------------------------
    | Supported Telemetry Provenance Types
    |--------------------------------------------------------------------------
    |
    | BR-300: Explicit provenance distinguishing real hardware from simulated
    | or fixture data.
    |
    */
    'telemetry_provenances' => ['hardware', 'simulation', 'fixture'],
];
