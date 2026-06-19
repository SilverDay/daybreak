-- Track when a degraded source last recovered so the UI can show a "recovered" badge.
ALTER TABLE sources
  ADD COLUMN last_recovered_at DATETIME NULL AFTER last_success_at;
