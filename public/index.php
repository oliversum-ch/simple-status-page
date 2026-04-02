<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$pdo = app_pdo();
$sites = fetch_public_sites($pdo);

public_layout('Status Beacon', function () use ($sites): void {
    ?>
    <section class="panel hero">
        <div>
            <div class="eyebrow">Shared Hosting Ready</div>
            <h1>Track downtime, email alerts, and publish clean status pages.</h1>
            <p class="subtitle">
                Add one or more websites, let cron run the checks, and share a public page for each property without needing any long-running worker.
            </p>
            <div class="inline-actions">
                <a class="button" href="<?= e(base_url('login.php')) ?>">Open Admin</a>
            </div>
        </div>
        <div class="panel" style="background: var(--panel-strong);">
            <div class="eyebrow">Overview</div>
            <p><strong><?= count($sites) ?></strong> tracked site<?= count($sites) === 1 ? '' : 's' ?></p>
            <p class="small">Each site gets its own public page and incident history.</p>
        </div>
    </section>

    <section class="grid site-grid">
        <?php if ($sites === []): ?>
            <article class="card">
                <h2>No sites configured yet</h2>
                <p class="meta">Log in to the admin area, add your first website, and then point cron to the monitor script.</p>
            </article>
        <?php endif; ?>

        <?php foreach ($sites as $site): ?>
            <article class="card stack">
                <div>
                    <div class="pill <?= e(site_status_class($site['current_status'])) ?>">
                        <?= e(site_status_label($site['current_status'])) ?>
                    </div>
                </div>
                <div>
                    <h2><?= e($site['name']) ?></h2>
                    <p class="meta"><?= e($site['site_url']) ?></p>
                </div>
                <p class="small">Last check: <?= e(format_checked_at($site['last_checked_at'])) ?></p>
                <a class="button secondary" href="<?= e(base_url('status.php?slug=' . urlencode($site['slug']))) ?>">View status page</a>
            </article>
        <?php endforeach; ?>
    </section>
    <?php
});
