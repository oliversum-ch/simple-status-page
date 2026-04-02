<?php

declare(strict_types=1);

function base_url(string $path = ''): string
{
    $base = rtrim((string) app_config('app.base_url', ''), '/');
    $path = ltrim($path, '/');

    if ($path === '') {
        return $base;
    }

    return $base . '/' . $path;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . base_url($path));
    exit;
}

function current_user_logged_in(): bool
{
    app_start_session();

    return !empty($_SESSION['is_admin']);
}

function require_admin(): void
{
    if (!current_user_logged_in()) {
        redirect('login.php');
    }
}

function csrf_token(): string
{
    app_start_session();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    app_start_session();

    $token = $_POST['_csrf'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) $token)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function flash(string $key, ?string $message = null): ?string
{
    app_start_session();

    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }

    $value = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);

    return $value;
}

function admin_layout(string $title, callable $content): void
{
    render_layout($title, $content, true);
}

function public_layout(string $title, callable $content): void
{
    render_layout($title, $content, false);
}

function render_layout(string $title, callable $content, bool $showAdminNav): void
{
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?></title>
        <link rel="stylesheet" href="<?= e(base_url('assets/app.css')) ?>">
    </head>
    <body>
    <div class="shell">
        <header class="topbar">
            <div>
                <a class="brand" href="<?= e(base_url('index.php')) ?>">Status Beacon</a>
                <p class="tagline">Lightweight uptime monitoring for shared hosting.</p>
            </div>
            <nav class="nav">
                <a href="<?= e(base_url('index.php')) ?>">Status Pages</a>
                <?php if ($showAdminNav && current_user_logged_in()): ?>
                    <a href="<?= e(base_url('admin.php')) ?>">Admin</a>
                    <a href="<?= e(base_url('logout.php')) ?>">Logout</a>
                <?php else: ?>
                    <a href="<?= e(base_url('login.php')) ?>">Admin Login</a>
                <?php endif; ?>
            </nav>
        </header>
        <main class="content">
            <?php $content(); ?>
        </main>
    </div>
    </body>
    </html>
    <?php
}

function site_status_label(?string $status): string
{
    return $status === 'down' ? 'Down' : 'Operational';
}

function site_status_class(?string $status): string
{
    return $status === 'down' ? 'is-down' : 'is-up';
}

function format_checked_at(?string $value): string
{
    if (!$value) {
        return 'Never checked yet';
    }

    return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value !== '' ? $value : 'site-' . bin2hex(random_bytes(3));
}

function generate_unique_slug(PDO $pdo, string $name, ?int $ignoreId = null): string
{
    $base = slugify($name);
    $slug = $base;
    $counter = 2;

    while (true) {
        $sql = 'SELECT COUNT(*) FROM websites WHERE slug = :slug';
        $params = ['slug' => $slug];

        if ($ignoreId !== null) {
            $sql .= ' AND id != :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }

        $slug = $base . '-' . $counter;
        $counter++;
    }
}

function fetch_sites(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT * FROM websites ORDER BY created_at DESC');
    return $stmt->fetchAll();
}

function fetch_public_sites(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT * FROM websites WHERE is_active = 1 AND show_on_dashboard = 1 ORDER BY name ASC');
    return $stmt->fetchAll();
}

function fetch_site_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM websites WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $site = $stmt->fetch();

    return $site ?: null;
}

function fetch_site_by_slug(PDO $pdo, string $slug): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM websites WHERE slug = :slug AND is_active = 1 LIMIT 1');
    $stmt->execute(['slug' => $slug]);
    $site = $stmt->fetch();

    return $site ?: null;
}

function fetch_site_checks(PDO $pdo, int $siteId, int $limit = 30): array
{
    $stmt = $pdo->prepare('SELECT * FROM checks WHERE website_id = :website_id ORDER BY checked_at DESC LIMIT ' . (int) $limit);
    $stmt->execute(['website_id' => $siteId]);
    return $stmt->fetchAll();
}

function fetch_site_checks_since(PDO $pdo, int $siteId, DateTimeImmutable $since): array
{
    $stmt = $pdo->prepare('
        SELECT status, checked_at
        FROM checks
        WHERE website_id = :website_id AND checked_at >= :since
        ORDER BY checked_at ASC
    ');
    $stmt->execute([
        'website_id' => $siteId,
        'since' => $since->format('Y-m-d H:i:s'),
    ]);

    return $stmt->fetchAll();
}

function fetch_site_incidents(PDO $pdo, int $siteId, int $limit = 10): array
{
    $stmt = $pdo->prepare('SELECT * FROM incidents WHERE website_id = :website_id ORDER BY started_at DESC LIMIT ' . (int) $limit);
    $stmt->execute(['website_id' => $siteId]);
    return $stmt->fetchAll();
}

function build_uptime_timeline(array $checks, int $days = 90): array
{
    $today = new DateTimeImmutable('today');
    $dayMap = [];
    $upChecks = 0;
    $totalChecks = count($checks);

    foreach ($checks as $check) {
        $dayKey = (new DateTimeImmutable($check['checked_at']))->format('Y-m-d');

        if (!isset($dayMap[$dayKey])) {
            $dayMap[$dayKey] = [
                'up_count' => 0,
                'down_count' => 0,
            ];
        }

        if ($check['status'] === 'down') {
            $dayMap[$dayKey]['down_count']++;
        } else {
            $dayMap[$dayKey]['up_count']++;
            $upChecks++;
        }
    }

    $timeline = [];

    for ($offset = $days - 1; $offset >= 0; $offset--) {
        $date = $today->modify('-' . $offset . ' days');
        $key = $date->format('Y-m-d');
        $counts = $dayMap[$key] ?? ['up_count' => 0, 'down_count' => 0];

        $state = 'no-data';
        if ($counts['down_count'] > 0 && $counts['up_count'] > 0) {
            $state = 'degraded';
        } elseif ($counts['down_count'] > 0) {
            $state = 'down';
        } elseif ($counts['up_count'] > 0) {
            $state = 'up';
        }

        $timeline[] = [
            'date' => $key,
            'state' => $state,
            'label' => uptime_bar_label($date, $counts, $state),
        ];
    }

    $uptimePercentage = $totalChecks > 0 ? round(($upChecks / $totalChecks) * 100, 2) : null;

    return [
        'days' => $timeline,
        'uptime_percentage' => $uptimePercentage,
        'total_checks' => $totalChecks,
    ];
}

function uptime_bar_label(DateTimeImmutable $date, array $counts, string $state): string
{
    $summary = match ($state) {
        'up' => 'Operational all day',
        'down' => 'Downtime detected',
        'degraded' => 'Partial downtime detected',
        default => 'No checks recorded',
    };

    $parts = [$date->format('M j, Y') . ': ' . $summary];

    if ($counts['up_count'] > 0 || $counts['down_count'] > 0) {
        $parts[] = sprintf('%d up / %d down checks', $counts['up_count'], $counts['down_count']);
    }

    return implode(' - ', $parts);
}

function format_uptime_percentage(?float $value): string
{
    if ($value === null) {
        return 'No uptime data yet';
    }

    return number_format($value, $value >= 99 ? 2 : 1) . ' % uptime';
}

function validate_site_input(array $input): array
{
    $errors = [];

    $name = trim((string) ($input['name'] ?? ''));
    $siteUrl = trim((string) ($input['site_url'] ?? ''));
    $checkUrl = trim((string) ($input['check_url'] ?? ''));
    $notificationEmail = trim((string) ($input['notification_email'] ?? ''));
    $checkInterval = max(1, (int) ($input['check_interval_minutes'] ?? 5));
    $timeoutSeconds = max(3, (int) ($input['timeout_seconds'] ?? 10));

    if ($name === '') {
        $errors[] = 'Name is required.';
    }

    if (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
        $errors[] = 'A valid site URL is required.';
    }

    if ($checkUrl !== '' && !filter_var($checkUrl, FILTER_VALIDATE_URL)) {
        $errors[] = 'Check URL must be empty or a valid URL.';
    }

    if (!filter_var($notificationEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid notification email is required.';
    }

    return [
        'errors' => $errors,
        'data' => [
            'name' => $name,
            'site_url' => $siteUrl,
            'check_url' => $checkUrl !== '' ? $checkUrl : $siteUrl,
            'notification_email' => $notificationEmail,
            'check_interval_minutes' => $checkInterval,
            'timeout_seconds' => $timeoutSeconds,
            'is_active' => !empty($input['is_active']) ? 1 : 0,
            'show_on_dashboard' => !empty($input['show_on_dashboard']) ? 1 : 0,
        ],
    ];
}

function send_notification_email(string $to, string $subject, string $message): bool
{
    $headers = [
        'From: ' . app_config('mail.from_name', 'Status Beacon') . ' <' . app_config('mail.from_email', 'monitor@example.com') . '>',
        'Reply-To: ' . app_config('mail.reply_to', app_config('mail.from_email', 'monitor@example.com')),
        'Content-Type: text/plain; charset=UTF-8',
    ];

    return mail($to, $subject, $message, implode("\r\n", $headers));
}

function monitor_http_endpoint(string $url, int $timeoutSeconds): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_NOBODY => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeoutSeconds,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_USERAGENT => 'StatusBeacon/1.0',
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false) {
            return [
                'is_up' => false,
                'http_code' => null,
                'error_message' => $error !== '' ? $error : 'Unknown cURL error',
                'response_time_ms' => null,
            ];
        }

        return [
            'is_up' => $httpCode >= 200 && $httpCode < 400,
            'http_code' => $httpCode,
            'error_message' => $httpCode >= 400 ? 'Unexpected HTTP status' : null,
            'response_time_ms' => null,
        ];
    }

    $start = microtime(true);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeoutSeconds,
            'ignore_errors' => true,
            'header' => "User-Agent: StatusBeacon/1.0\r\n",
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $responseTime = (int) round((microtime(true) - $start) * 1000);
    $headers = $http_response_header ?? [];

    $httpCode = null;
    foreach ($headers as $header) {
        if (preg_match('#HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
            $httpCode = (int) $matches[1];
        }
    }

    if ($body === false && $httpCode === null) {
        return [
            'is_up' => false,
            'http_code' => null,
            'error_message' => 'Connection failed',
            'response_time_ms' => $responseTime,
        ];
    }

    return [
        'is_up' => $httpCode !== null && $httpCode >= 200 && $httpCode < 400,
        'http_code' => $httpCode,
        'error_message' => $httpCode !== null && $httpCode >= 400 ? 'Unexpected HTTP status' : null,
        'response_time_ms' => $responseTime,
    ];
}
