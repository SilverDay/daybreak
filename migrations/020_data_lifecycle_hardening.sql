-- Phase 2 hardening: enforce referential integrity for watch terms and
-- webhook logs so account deletion cleans up dependent records.

-- user_watch_terms.user_id was INT without FK; align type with users.id and
-- enforce cascading deletion.
DELETE FROM user_watch_terms
WHERE user_id NOT IN (SELECT id FROM users);

ALTER TABLE user_watch_terms
  MODIFY user_id BIGINT UNSIGNED NOT NULL;

ALTER TABLE user_watch_terms
  ADD CONSTRAINT fk_watch_terms_user
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- webhook_log had no FK to user_webhooks; clean orphans and enforce cascade.
DELETE wl
FROM webhook_log wl
LEFT JOIN user_webhooks uw ON uw.id = wl.webhook_id
WHERE uw.id IS NULL;

ALTER TABLE webhook_log
  ADD CONSTRAINT fk_webhook_log_webhook
  FOREIGN KEY (webhook_id) REFERENCES user_webhooks(id) ON DELETE CASCADE;
