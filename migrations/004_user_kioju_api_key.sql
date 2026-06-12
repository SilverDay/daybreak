-- Store per-user Kioju API credentials (encrypted at rest)
ALTER TABLE users
  ADD COLUMN kioju_api_key_enc TEXT NULL AFTER last_seen_at,
  ADD COLUMN kioju_connected_at DATETIME NULL AFTER kioju_api_key_enc;
