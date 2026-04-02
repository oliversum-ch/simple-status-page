<?php

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$configFile = APP_ROOT . '/config.php';

if (!is_file($configFile)) {
    $configFile = APP_ROOT . '/config.sample.php';
}

$config = require $configFile;

if (!is_array($config)) {
    throw new RuntimeException('Configuration file must return an array.');
}

require_once APP_ROOT . '/app/functions.php';

date_default_timezone_set($config['app']['timezone'] ?? 'UTC');

function app_config(?string $key = null, mixed $default = null): mixed
{
    global $config;

    if ($key === null) {
        return $config;
    }

    $segments = explode('.', $key);
    $value = $config;

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

function app_pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        app_config('db.host', '127.0.0.1'),
        (int) app_config('db.port', 3306),
        app_config('db.database', '')
    );

    $pdo = new PDO(
        $dsn,
        (string) app_config('db.username', ''),
        (string) app_config('db.password', ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $pdo;
}

function app_start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('status_page_admin');
        session_start();
    }
}
