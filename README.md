# Status Beacon

Simple website downtime checker and public status page built for shared webhosting with PHP, MariaDB, and cron jobs.

## Features

- Track one or more websites from a small password-protected admin panel
- Send email notifications when a monitored website changes from up to down
- Publish one public status page per tracked website
- Run checks through a cron job instead of a long-running worker
- Store checks and incidents in MariaDB

## Structure

- `public/` web root
- `app/` bootstrap and helper functions
- `cron/check_sites.php` scheduled monitor script
- `database.sql` MariaDB schema
- `config.sample.php` configuration template

## Setup

1. Copy `config.sample.php` to `config.php`.
2. Generate a password hash:

```php
<?php
echo password_hash('change-me', PASSWORD_DEFAULT), PHP_EOL;
```

3. Paste the generated hash into `config.php` under `app.admin_password_hash`.
4. Create a MariaDB database and import `database.sql`.
5. Update `app.base_url`, database credentials, and mail settings in `config.php`.
6. Set `app.base_url` to the actual public web path of this app.
7. If your host lets you choose the document root, point it to `public/` and use a base URL like `https://status.example.com`.
8. If your host cannot change the document root, upload the project in a visible subdirectory and use a base URL like `https://example.com/status/public`.
9. Add a cron job similar to:

```bash
*/5 * * * * /usr/bin/php /home/USER/path/to/project/cron/check_sites.php
```

10. Open `/login.php`, sign in, and add your websites.

## Notes

- The monitor treats HTTP `200-399` as up and `400+` or connection failures as down.
- Email delivery uses PHP's built-in `mail()` function so it works on typical shared hosting environments.
- The current public status page is intentionally simple and reads directly from the latest checks and incidents.
