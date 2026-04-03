<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$pdo = app_pdo();
$site = $slug !== '' ? fetch_site_by_slug($pdo, $slug) : null;

if ($site === null) {
    http_response_code(404);

    public_layout('Status Page Not Found', function (): void {
        ?>
        <section class="panel">
            <h1>Status page not found</h1>
            <p class="subtitle">This site is either inactive or the slug is invalid.</p>
        </section>
        <?php
    });
    exit;
}

$checks = fetch_site_checks($pdo, (int) $site['id'], 20);
$incidents = fetch_site_incidents($pdo, (int) $site['id'], 10);
$uptimeChecks = fetch_site_checks_since($pdo, (int) $site['id'], new DateTimeImmutable('-29 days midnight'));
$uptimeTimeline = build_uptime_timeline($uptimeChecks, 30);

public_layout($site['name'] . ' Status', function () use ($site, $checks, $incidents, $uptimeTimeline): void {
    ?>
    <section class="panel hero">
        <div>
            <div class="eyebrow">Public Status Page</div>
            <h1><?= e($site['name']) ?></h1>
            <p class="subtitle">Current state for <?= e($site['site_url']) ?> with recent checks and resolved incidents.</p>
            <p><a href="<?= e($site['site_url']) ?>" target="_blank" rel="noopener noreferrer">Visit website</a></p>
        </div>
        <div class="panel" style="background: var(--panel-strong);">
            <div class="pill <?= e(site_status_class($site['current_status'])) ?>">
                <?= e(site_status_label($site['current_status'])) ?>
            </div>
            <p class="small">Last checked: <?= e(format_checked_at($site['last_checked_at'])) ?></p>
            <p class="small">Last HTTP code: <?= e($site['last_http_code'] ? (string) $site['last_http_code'] : 'n/a') ?></p>
            <p class="small">Last error: <?= e($site['last_error_message'] ?: 'None') ?></p>
        </div>
    </section>

    <section class="panel stack">
        <div>
            <div class="eyebrow">30-Day Uptime</div>
            <h2>Availability history</h2>
        </div>
        <div class="uptime-strip-wrap">
            <div class="uptime-strip" role="img" aria-label="<?= e(format_uptime_percentage($uptimeTimeline['uptime_percentage'])) ?>">
                <?php foreach ($uptimeTimeline['days'] as $day): ?>
                    <span
                        class="uptime-bar uptime-<?= e($day['state']) ?>"
                        title="<?= e($day['label']) ?>"
                        style="display:inline-block;width:12px;height:44px;background:<?= e(uptime_bar_color($day['state'])) ?>;border:1px solid rgba(29, 42, 37, 0.08);border-radius:4px;vertical-align:bottom;"
                        aria-hidden="true"
                    >&nbsp;</span>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="uptime-legend">
            <span>30 days ago</span>
            <span><?= e(format_uptime_percentage($uptimeTimeline['uptime_percentage'])) ?></span>
            <span>Today</span>
        </div>
    </section>

    <section class="grid" style="grid-template-columns: 1.15fr 0.85fr;">
        <article class="panel">
            <h2>Recent checks</h2>
            <table class="table">
                <thead>
                <tr>
                    <th>Checked at</th>
                    <th>Status</th>
                    <th>HTTP</th>
                    <th>Response</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($checks as $check): ?>
                    <tr>
                        <td><?= e(format_checked_at($check['checked_at'])) ?></td>
                        <td><?= e(strtoupper($check['status'])) ?></td>
                        <td><?= e($check['http_code'] ? (string) $check['http_code'] : 'n/a') ?></td>
                        <td><?= e($check['error_message'] ?: 'OK') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </article>

        <article class="panel">
            <h2>Incident history</h2>
            <div class="stack">
                <?php if ($incidents === []): ?>
                    <p class="small">No incidents recorded yet.</p>
                <?php endif; ?>

                <?php foreach ($incidents as $incident): ?>
                    <div class="card">
                        <div class="pill <?= $incident['status'] === 'open' ? 'is-down' : 'is-up' ?>">
                            <?= e($incident['status'] === 'open' ? 'Ongoing incident' : 'Resolved incident') ?>
                        </div>
                        <p class="small">Started: <?= e(format_checked_at($incident['started_at'])) ?></p>
                        <p class="small">Resolved: <?= e(format_checked_at($incident['resolved_at'])) ?></p>
                        <p class="small">Details: <?= e($incident['error_message'] ?: 'No extra details') ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
    </section>
    <?php
});
