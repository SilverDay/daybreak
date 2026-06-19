CREATE TABLE user_starred_articles (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      BIGINT UNSIGNED NOT NULL,
  article_id   BIGINT UNSIGNED NOT NULL,
  url          VARCHAR(1000) NOT NULL,
  title        VARCHAR(500) NOT NULL,
  source_name  VARCHAR(200) NOT NULL,
  published_at DATETIME NULL,
  starred_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_article (user_id, article_id),
  KEY          idx_user_starred (user_id, starred_at),
  CONSTRAINT fk_starred_articles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
