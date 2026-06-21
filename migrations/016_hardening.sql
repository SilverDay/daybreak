-- Production hardening: rate-limiting type column + performance indexes.

-- 1. Add type to login_attempts so register/forgot/login attempts are distinguishable.
ALTER TABLE login_attempts
    ADD COLUMN type VARCHAR(20) NOT NULL DEFAULT 'login' AFTER email,
    ADD KEY idx_type_ip_time (type, ip_hash, created_at);

-- 2. Composite index for dashboard fetch_log scalar subqueries that filter on status.
ALTER TABLE fetch_log
    ADD KEY idx_source_status_time (source_id, status, created_at);

-- 3. Explicit FK-covering index on user_webhooks for per-user queries.
ALTER TABLE user_webhooks
    ADD KEY idx_user_id (user_id);

-- 4. Composite index for the items_today subquery in the admin dashboard.
ALTER TABLE articles
    ADD KEY idx_source_fetched (source_id, fetched_at);
