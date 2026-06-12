# Daybreak Feature Backlog

This file captures the remaining improvements identified during the current implementation and test-hardening pass, so work can resume quickly in the next session.

## Ready Next

### 0. ModSecurity Hardening Follow-Up
- Goal: keep the site stable without permanently broad WAF response-scan bypasses.
- Why: production outage was caused by CRS RESPONSE-951 PCRE limit exhaustion on front-controller responses.
- Current mitigation in place:
  - vhost rule disables ModSecurity response-body inspection for `/` and `/index.php`.
- Scope:
  - Confirm 24h stability and no recurring RESPONSE-951 execution errors.
  - Replace route-level `responseBodyAccess=Off` with a narrower exclusion for the specific RESPONSE-951 checks.
  - Re-test key pages (`/`, `/feed`, `/suggest`, category pages) after tightening.
  - Keep a rollback snippet ready for rapid recovery.
- Likely files:
  - `deploy/apache-vhost.conf`
  - `docs/SPEC.md`
  - `README.md`

### 1. Source Preview And Validation (Implemented)
- Goal: make source onboarding safer and faster for admins.
- Why: the current admin flow allows source creation/editing, but it does not preview fetch results before save, and adapter-specific validation is still shallow.
- Scope:
  - Add a preview action in the admin source form.
  - Fetch a small sample through `FeedFetcher` using the selected adapter.
  - Show parsed title, URL, published time, and summary sample before save.
  - Validate `json_api` field maps against the preview payload.
  - Reject unsupported adapter choices early and clearly.
- Likely files:
  - `src/Controller/AdminController.php`
  - `src/View/admin/sources/edit.php`
  - `src/Service/FeedFetcher.php`
  - `src/Adapter/JsonApiAdapter.php`

### 2. Fetch Health Observability (Implemented)
- Goal: make failing or stale sources obvious without reading raw logs.
- Why: admin health data exists, but it is still mostly reactive.
- Scope:
  - Highlight sources with repeated zero-item fetches.
  - Highlight rising failure streaks before auto-disable.
  - Show time since last successful fetch more prominently.
  - Add a small summary panel for degraded and auto-disabled sources.
  - Consider surfacing average fetch duration and last HTTP status.
- Likely files:
  - `src/Controller/AdminController.php`
  - `src/View/admin/dashboard.php`
  - `src/View/admin/sources/list.php`

### 3. Since-Last-Visit Semantics (Implemented)
- Goal: avoid marking content as seen too early.
- Why: the personalized feed currently advances `last_seen_at` on page load.
- Scope:
  - Add an explicit “mark feed as seen” action, or
  - Update `last_seen_at` only after a user-confirmed action, or
  - Introduce a minimal read-state model if needed.
- Current control point:
  - `src/Controller/FeedController.php`

## Product-Sized Follow-Ups

### 4. Search
- Add headline and summary search across cached articles.
- Support filters for category, source, and time window.
- Good follow-up once the core feed workflow is fully stable.

### 5. Bookmarks 
- Allow users to store files in their kioju.de account
- This needs a configuration of the API-key for kioju in the user profile
- kioju documentation is available at https://kioju.de/api_docs.php

### 6. Email Digests
- Add scheduled digests for:
  - since last visit
  - critical advisories
  - recent CVEs
- Reuse the existing mail infrastructure.

### 7. Trust / Freshness Scoring
- Surface source health and freshness in the UI.
- Show whether a source is healthy, stale, degraded, or recently recovered.

## Implemented In This Session
- Since-last-visit behavior switched to explicit user confirmation: `last_seen_at` is no longer advanced on feed page load.
- Added authenticated CSRF-protected `POST /feed/mark-seen` flow with safe local return-path validation.
- Personalised feed banner now includes a “Mark feed as seen” action; read-state updates only on user intent.
- Added focused controller tests for return-path sanitization/open-redirect prevention.
- Dashboard/source-list fetch health observability: repeated zero-yield trend markers, rising failure streak highlighting, and prominent last-success recency.
- Dashboard summary panel for degraded, auto-disabled, zero-yield trend, and stale-over-24h source counts.
- Last fetch HTTP status and average fetch duration surfaced in admin health tables.
- Source preview action for admin source create/edit form with parsed sample output before save.
- Shared source input validation with early unsupported-adapter rejection.
- JSON API preview diagnostics for field map issues (invalid JSON/object shape, missing paths, unusable URL mappings).
- New `SourcePreviewService` orchestration and adapter-backed preview test coverage.
- Native PHP test suite in `tests/`.
- CI workflow for tests and PHP lint.
- Redirect resolution fix in `FeedFetcher`.
- Adapter unit coverage for RSS, JSON API, NVD, and ransomlook.
- Extracted DB-free auth logic for direct unit testing.
- Removed unsupported `html_scrape` from admin source management.
- Extracted auth email body building into a dedicated helper.

## Recommended Next Start
- Start with “Search”.
- It is the best next engineering step because onboarding, health observability, and since-last-visit semantics are now implemented, making search the next highest-impact user feature.
