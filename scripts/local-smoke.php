<?php

declare(strict_types=1);

$baseUrl = $argv[1] ?? 'http://127.0.0.1:8000';

$targets = [
    ['path' => '/', 'accepted' => [200]],
    ['path' => '/login', 'accepted' => [200]],
    ['path' => '/register', 'accepted' => [200]],
    ['path' => '/admin/dashboard', 'accepted' => [302, 401, 403]],
    ['path' => '/admin/integrations/providers', 'accepted' => [302, 401, 403]],
    ['path' => '/agency/dashboard', 'accepted' => [302, 401, 403]],
    ['path' => '/customer/login', 'accepted' => [200]],
    ['path' => '/customer/dashboard', 'accepted' => [302, 401, 403]],
    ['path' => '/api/v1/health', 'accepted' => [200]],
    ['path' => '/api/v1/packages', 'accepted' => [200]],
];

$failed = false;

foreach ($targets as $target) {
    $path = $target['path'];
    $accepted = $target['accepted'];
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'ignore_errors' => true,
            'header' => "User-Agent: local-smoke-check\r\n",
        ],
    ]);

    @file_get_contents($baseUrl.$path, false, $context);
    $statusLine = $http_response_header[0] ?? 'NO_RESPONSE';

    if (! preg_match('/\s(\d{3})\s/', $statusLine, $match)) {
        $failed = true;
        echo sprintf("%-36s %s\n", $path, 'ERROR');
        continue;
    }

    $code = (int) $match[1];
    $ok = in_array($code, $accepted, true);

    if (! $ok) {
        $failed = true;
    }

    echo sprintf("%-36s %d (expected: %s)\n", $path, $code, implode('/', $accepted));
}

exit($failed ? 1 : 0);
