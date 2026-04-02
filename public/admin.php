<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

require_admin();

$pdo = app_pdo();
$editingId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$editingSite = $editingId ? fetch_site_by_id($pdo, $editingId) : null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_site') {
        $validation = validate_site_input($_POST);
        $errors = $validation['errors'];
        $data = $validation['data'];
        $siteId = (int) ($_POST['site_id'] ?? 0);

        if ($errors === []) {
            if ($siteId > 0) {
                $existing = fetch_site_by_id($pdo, $siteId);

                if ($existing === null) {
                    $errors[] = 'Site not found.';
                } else {
                    $slug = generate_unique_slug($pdo, $data['name'], $siteId);
                    $stmt = $pdo->prepare('
                        UPDATE websites
                        SET name = :name, slug = :slug, site_url = :site_url, check_url = :check_url,
                            notification_email = :notification_email, check_interval_minutes = :check_interval_minutes,
                            timeout_seconds = :timeout_seconds, is_active = :is_active,
                            show_on_dashboard = :show_on_dashboard
                        WHERE id = :id
                    ');
                    $stmt->execute($data + ['slug' => $slug, 'id' => $siteId]);
                    flash('notice', 'Site updated.');
                    redirect('admin.php');
                }
            } else {
                $slug = generate_unique_slug($pdo, $data['name']);
                $stmt = $pdo->prepare('
                    INSERT INTO websites
                        (name, slug, site_url, check_url, notification_email, check_interval_minutes, timeout_seconds, is_active, show_on_dashboard)
                    VALUES
                        (:name, :slug, :site_url, :check_url, :notification_email, :check_interval_minutes, :timeout_seconds, :is_active, :show_on_dashboard)
                ');
                $stmt->execute($data + ['slug' => $slug]);
                flash('notice', 'Site created.');
                redirect('admin.php');
            }
        }

        $editingSite = array_merge($editingSite ?? [], $data, ['id' => $siteId]);
    }

    if ($action === 'delete_site') {
        $siteId = (int) ($_POST['site_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM websites WHERE id = :id');
        $stmt->execute(['id' => $siteId]);
        flash('notice', 'Site deleted.');
        redirect('admin.php');
    }
}

$sites = fetch_sites($pdo);
$notice = flash('notice');
$formDefaults = $editingSite ?? [
    'id' => 0,
    'name' => '',
    'site_url' => '',
    'check_url' => '',
    'notification_email' => '',
    'check_interval_minutes' => 5,
    'timeout_seconds' => 10,
    'is_active' => 1,
    'show_on_dashboard' => 1,
];

admin_layout('Admin', function () use ($sites, $notice, $errors, $formDefaults): void {
    ?>
    <section class="panel stack">
        <div>
            <div class="eyebrow">Admin</div>
            <h1>Website monitoring dashboard</h1>
            <p class="subtitle">Add websites, choose where health checks should run, and publish a public status page for each site.</p>
        </div>

        <?php if ($notice): ?>
            <div class="notice"><?= e($notice) ?></div>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <div class="errors">
                <?= e(implode(' ', $errors)) ?>
            </div>
        <?php endif; ?>

        <div class="panel" style="background: var(--panel-strong);">
            <h2><?= (int) $formDefaults['id'] > 0 ? 'Edit monitored site' : 'Add monitored site' ?></h2>
            <form method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_site">
                <input type="hidden" name="site_id" value="<?= e((string) $formDefaults['id']) ?>">

                <div class="form-grid">
                    <label>
                        Name
                        <input type="text" name="name" value="<?= e($formDefaults['name']) ?>" required>
                    </label>
                    <label>
                        Public website URL
                        <input type="url" name="site_url" value="<?= e($formDefaults['site_url']) ?>" required>
                    </label>
                    <label>
                        Check URL
                        <input type="url" name="check_url" value="<?= e($formDefaults['check_url']) ?>" placeholder="Leave empty to use the public URL">
                    </label>
                    <label>
                        Notification email
                        <input type="email" name="notification_email" value="<?= e($formDefaults['notification_email']) ?>" required>
                    </label>
                    <label>
                        Check interval in minutes
                        <input type="number" min="1" name="check_interval_minutes" value="<?= e((string) $formDefaults['check_interval_minutes']) ?>" required>
                    </label>
                    <label>
                        Timeout in seconds
                        <input type="number" min="3" name="timeout_seconds" value="<?= e((string) $formDefaults['timeout_seconds']) ?>" required>
                    </label>
                </div>

                <label class="small">
                    <input type="checkbox" name="is_active" value="1" <?= !empty($formDefaults['is_active']) ? 'checked' : '' ?>>
                    Track this website and show its public status page
                </label>

                <label class="small">
                    <input type="checkbox" name="show_on_dashboard" value="1" <?= !empty($formDefaults['show_on_dashboard']) ? 'checked' : '' ?>>
                    Show this website on the public Status Beacon dashboard
                </label>

                <div class="inline-actions">
                    <button type="submit"><?= (int) $formDefaults['id'] > 0 ? 'Save changes' : 'Add website' ?></button>
                    <?php if ((int) $formDefaults['id'] > 0): ?>
                        <a class="button secondary" href="<?= e(base_url('admin.php')) ?>">Cancel edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </section>

    <section class="panel">
        <h2>Tracked websites</h2>
        <table class="table">
            <thead>
            <tr>
                <th>Site</th>
                <th>Status</th>
                <th>Last check</th>
                <th>Dashboard</th>
                <th>Status page</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($sites as $site): ?>
                <tr>
                    <td>
                        <strong><?= e($site['name']) ?></strong><br>
                        <span class="small"><?= e($site['check_url']) ?></span>
                    </td>
                    <td>
                        <span class="pill <?= e(site_status_class($site['current_status'])) ?>">
                            <?= e(site_status_label($site['current_status'])) ?>
                        </span>
                    </td>
                    <td><?= e(format_checked_at($site['last_checked_at'])) ?></td>
                    <td><?= !empty($site['show_on_dashboard']) ? 'Visible' : 'Hidden' ?></td>
                    <td><a href="<?= e(base_url('status.php?slug=' . urlencode($site['slug']))) ?>">Open page</a></td>
                    <td>
                        <div class="inline-actions">
                            <a class="button secondary" href="<?= e(base_url('admin.php?edit=' . $site['id'])) ?>">Edit</a>
                            <form method="post" onsubmit="return confirm('Delete this website?');">
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete_site">
                                <input type="hidden" name="site_id" value="<?= e((string) $site['id']) ?>">
                                <button type="submit">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php
});
