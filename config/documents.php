<?php

$appEnv = strtolower((string) env('APP_ENV', 'production'));
$defaultMode = match ($appEnv) {
    'local' => 'stub',
    'testing', 'test' => 'stub',
    'staging' => 'real',
    default => 'real',
};

$defaultProvider = match ($appEnv) {
    'local', 'testing', 'test' => 'stub',
    default => 'clamav',
};

return [
    'upload' => [
        'max_file_size_bytes' => (int) env('DOCUMENT_UPLOAD_MAX_FILE_SIZE_BYTES', 10 * 1024 * 1024),
        'allowlists' => [
            'images' => [
                'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
                'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
            ],
            'documents' => [
                'mimes' => ['application/pdf'],
                'extensions' => ['pdf'],
            ],
        ],
        'blocked_extensions' => [
            'exe', 'dll', 'bat', 'cmd', 'ps1', 'sh', 'php', 'phtml', 'phar', 'js', 'jar', 'com', 'msi', 'scr',
        ],
        'blocked_mimes' => [
            'application/x-dosexec',
            'application/x-msdownload',
            'application/x-sh',
            'application/x-httpd-php',
            'text/x-php',
            'application/javascript',
            'text/javascript',
        ],
    ],

    'scanning' => [
        // disabled|stub|real
        'mode' => env('DOCUMENT_SCAN_MODE', $defaultMode),

        // Provider key used by DocumentScanService in real mode.
        'provider' => env('DOCUMENT_SCAN_PROVIDER', $defaultProvider),

        // In production we fail closed: skip/disable should not be considered safe.
        'require_real_scanner_in_production' => (bool) env('DOCUMENT_SCAN_REQUIRE_REAL_IN_PROD', true),

        // Stub mode controls for local/dev/test. Keep deterministic in tests.
        'stub_status' => env('DOCUMENT_SCAN_STUB_STATUS', 'clean'),
        'stub_message' => env('DOCUMENT_SCAN_STUB_MESSAGE', 'Stub scan result'),
        'stub_sequence' => env('DOCUMENT_SCAN_STUB_SEQUENCE'),

        // Defensive size cap for scanner payload.
        'max_file_size_bytes' => (int) env('DOCUMENT_SCAN_MAX_FILE_SIZE_BYTES', 20 * 1024 * 1024),

        'clamav' => [
            'host' => env('CLAMAV_HOST', '127.0.0.1'),
            'port' => (int) env('CLAMAV_PORT', 3310),
            'timeout_seconds' => (int) env('CLAMAV_TIMEOUT_SECONDS', 5),
        ],
    ],
];
