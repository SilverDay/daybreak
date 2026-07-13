-- Per-user widget source assignment for right-rail slot customization.
-- slot=1 and slot=2 map to the two fixed widget positions.
-- source_id NULL means "use slot default content".

CREATE TABLE user_widget_sources (
  user_id BIGINT UNSIGNED NOT NULL,
  slot TINYINT UNSIGNED NOT NULL,
  source_id INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, slot),
  KEY idx_widget_source (source_id),
  CONSTRAINT fk_uws_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_uws_source FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE SET NULL,
  CONSTRAINT chk_widget_slot CHECK (slot IN (1,2))
);
