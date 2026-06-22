-- Per-source User-Agent override.
-- When set, the fetcher uses this UA instead of the global FETCH_USER_AGENT default.
-- Allows sources that reject browser UAs (e.g. SANS ISC) to use the bot UA while
-- Cloudflare-fronted sources can keep the browser UA.

ALTER TABLE sources
  ADD COLUMN user_agent_override VARCHAR(255) NULL AFTER field_map;
