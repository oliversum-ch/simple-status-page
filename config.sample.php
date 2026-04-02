<?php

declare(strict_types=1);

return [
    'app' => [
        'base_url' => 'https://example.com/simple-status-page',
        'timezone' => 'Europe/Zurich',
        'admin_password_hash' => '$2y$10$replace_this_with_password_hash',
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'status_beacon',
        'username' => 'db_user',
        'password' => 'db_pass',
    ],
    'mail' => [
        'from_email' => 'monitor@example.com',
        'from_name' => 'Status Beacon',
        'reply_to' => 'support@example.com',
    ],
];
