CREATE TABLE IF NOT EXISTS websites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(160) NOT NULL UNIQUE,
    site_url VARCHAR(255) NOT NULL,
    check_url VARCHAR(255) NOT NULL,
    notification_email VARCHAR(190) NOT NULL,
    current_status ENUM('up', 'down') NOT NULL DEFAULT 'up',
    last_http_code SMALLINT UNSIGNED NULL,
    last_error_message VARCHAR(255) NULL,
    last_checked_at DATETIME NULL,
    check_interval_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    timeout_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    show_on_dashboard TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS checks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    website_id INT UNSIGNED NOT NULL,
    status ENUM('up', 'down') NOT NULL,
    http_code SMALLINT UNSIGNED NULL,
    error_message VARCHAR(255) NULL,
    response_time_ms INT UNSIGNED NULL,
    checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_checks_website FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE,
    INDEX idx_checks_website_checked (website_id, checked_at)
);

CREATE TABLE IF NOT EXISTS incidents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    website_id INT UNSIGNED NOT NULL,
    status ENUM('open', 'resolved') NOT NULL DEFAULT 'open',
    started_at DATETIME NOT NULL,
    resolved_at DATETIME NULL,
    started_http_code SMALLINT UNSIGNED NULL,
    resolved_http_code SMALLINT UNSIGNED NULL,
    error_message VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_incidents_website FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE,
    INDEX idx_incidents_website_started (website_id, started_at)
);
