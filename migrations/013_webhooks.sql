-- Outbound webhooks: push new articles to Slack, Discord, or generic HTTP endpoints.
CREATE TABLE user_webhooks (
    id          INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    name        VARCHAR(120) NOT NULL,
    url         VARCHAR(2000) NOT NULL,
    format      ENUM('slack','discord','generic') NOT NULL DEFAULT 'generic',
    filter_json JSON NULL,
    active      TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_webhooks_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE webhook_log (
    id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    webhook_id    INT UNSIGNED NOT NULL,
    article_url   VARCHAR(1000) NOT NULL,
    article_title VARCHAR(500) NOT NULL,
    status        ENUM('ok','failed','retry_ok','retry_failed') NOT NULL,
    http_status   SMALLINT UNSIGNED NULL,
    attempt       TINYINT UNSIGNED NOT NULL DEFAULT 1,
    error         VARCHAR(500) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_wh_status (webhook_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
