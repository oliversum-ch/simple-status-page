<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

app_start_session();

if (current_user_logged_in()) {
    redirect('admin.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $password = (string) ($_POST['password'] ?? '');
    $hash = (string) app_config('app.admin_password_hash', '');

    if ($hash !== '' && password_verify($password, $hash)) {
        $_SESSION['is_admin'] = true;
        flash('notice', 'Welcome back.');
        redirect('admin.php');
    }

    $error = 'Invalid password.';
}

public_layout('Admin Login', function () use ($error): void {
    ?>
    <section class="panel" style="max-width: 560px; margin: 0 auto;">
        <div class="eyebrow">Admin Login</div>
        <h1>Manage monitored websites</h1>
        <p class="subtitle">Use the password hash in `config.php` to protect the admin area on shared hosting.</p>

        <?php if ($error): ?>
            <div class="errors"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <label>
                Password
                <input type="password" name="password" required>
            </label>
            <button type="submit">Login</button>
        </form>
    </section>
    <?php
});
