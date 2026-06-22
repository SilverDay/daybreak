-- CrowdStrike (and a few other sources) generate a random ?p=XXXX GUID on every feed
-- response, so ON DUPLICATE KEY on (source_id, guid) never fires and the same articles
-- get re-inserted on every full fetch.  The dedup_key (title+date fingerprint) already
-- catches this but had no UNIQUE constraint.
--
-- Step 1: remove duplicates, keeping the earliest-inserted row per (source_id, dedup_key).
DELETE a FROM articles a
INNER JOIN (
    SELECT source_id, dedup_key, MIN(id) AS keep_id
    FROM articles
    WHERE dedup_key IS NOT NULL
    GROUP BY source_id, dedup_key
    HAVING COUNT(*) > 1
) AS dups
  ON  a.source_id  = dups.source_id
  AND a.dedup_key  = dups.dedup_key
  AND a.id        != dups.keep_id;

-- Step 2: enforce uniqueness going forward.
-- NULL dedup_key values are exempt (MariaDB treats each NULL as distinct).
ALTER TABLE articles
  ADD UNIQUE KEY uq_source_dedup (source_id, dedup_key);
