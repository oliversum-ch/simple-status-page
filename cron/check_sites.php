<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$pdo = app_pdo();
$now = new DateTimeImmutable('now');
$sites = $pdo->query('SELECT * FROM websites WHERE is_active = 1 ORDER BY id ASC')->fetchAll();

foreach ($sites as $site) {
    $lastCheckedAt = $site['last_checked_at'] ? new DateTimeImmutable($site['last_checked_at']) : null;

    if ($lastCheckedAt !== null) {
        $nextDueAt = $lastCheckedAt->modify('+' . (int) $site['check_interval_minutes'] . ' minutes');

        if ($nextDueAt > $now) {
            continue;
        }
    }

    $result = monitor_http_endpoint($site['check_url'], (int) $site['timeout_seconds']);
    $status = $result['is_up'] ? 'up' : 'down';
    $checkedAt = $now->format('Y-m-d H:i:s');

    $pdo->beginTransaction();

    try {
        $insertCheck = $pdo->prepare('
            INSERT INTO checks (website_id, status, http_code, error_message, response_time_ms, checked_at)
            VALUES (:website_id, :status, :http_code, :error_message, :response_time_ms, :checked_at)
        ');
        $insertCheck->execute([
            'website_id' => $site['id'],
            'status' => $status,
            'http_code' => $result['http_code'],
            'error_message' => $result['error_message'],
            'response_time_ms' => $result['response_time_ms'],
            'checked_at' => $checkedAt,
        ]);

        $updateWebsite = $pdo->prepare('
            UPDATE websites
            SET current_status = :current_status,
                last_http_code = :last_http_code,
                last_error_message = :last_error_message,
                last_checked_at = :last_checked_at
            WHERE id = :id
        ');
        $updateWebsite->execute([
            'current_status' => $status,
            'last_http_code' => $result['http_code'],
            'last_error_message' => $result['error_message'],
            'last_checked_at' => $checkedAt,
            'id' => $site['id'],
        ]);

        $previousStatus = $site['current_status'];

        if ($previousStatus !== 'down' && $status === 'down') {
            $openIncident = $pdo->prepare('
                INSERT INTO incidents (website_id, status, started_at, started_http_code, error_message)
                VALUES (:website_id, :status, :started_at, :started_http_code, :error_message)
            ');
            $openIncident->execute([
                'website_id' => $site['id'],
                'status' => 'open',
                'started_at' => $checkedAt,
                'started_http_code' => $result['http_code'],
                'error_message' => $result['error_message'],
            ]);
        }

        if ($previousStatus === 'down' && $status === 'up') {
            $resolveIncident = $pdo->prepare('
                UPDATE incidents
                SET status = :status, resolved_at = :resolved_at, resolved_http_code = :resolved_http_code
                WHERE website_id = :website_id AND status = :open_status
                ORDER BY id DESC
                LIMIT 1
            ');
            $resolveIncident->execute([
                'status' => 'resolved',
                'resolved_at' => $checkedAt,
                'resolved_http_code' => $result['http_code'],
                'website_id' => $site['id'],
                'open_status' => 'open',
            ]);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        fwrite(STDERR, sprintf("Failed to process site %d: %s\n", $site['id'], $exception->getMessage()));
        continue;
    }

    if ($previousStatus !== 'down' && $status === 'down') {
        $statusPageUrl = base_url('status.php?slug=' . $site['slug']);
        $subject = '[Status Beacon] ' . $site['name'] . ' is down';
        $message = implode("\n", [
            'The monitored site appears to be down.',
            '',
            'Site: ' . $site['name'],
            'Public URL: ' . $site['site_url'],
            'Check URL: ' . $site['check_url'],
            'Checked at: ' . $checkedAt,
            'HTTP code: ' . ($result['http_code'] !== null ? $result['http_code'] : 'n/a'),
            'Error: ' . ($result['error_message'] ?? 'Unknown issue'),
            'Status page: ' . $statusPageUrl,
        ]);

        send_notification_email($site['notification_email'], $subject, $message);
    }
}

echo sprintf("Processed %d site(s).\n", count($sites));
