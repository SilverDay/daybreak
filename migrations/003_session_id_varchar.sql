-- Daybreak — 003_session_id_varchar.sql
-- Widen sessions.id to VARCHAR(128) so PHP's default session ID lengths fit
-- without requiring ini-level configuration tricks.
ALTER TABLE sessions MODIFY COLUMN id VARCHAR(128) NOT NULL;
