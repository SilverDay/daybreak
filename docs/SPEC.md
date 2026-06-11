# Daybreak — Security News Aggregator

**Software Specification**

> **Name:** *Daybreak* — confirmed via deployment subdomain `daybreak.silverday.de`.

| | |
|---|---|
| **Project** | Daybreak — self-hosted security news aggregator |
| **Owner** | Klaus-E. Klingner / SilverDay Media |
| **Document** | `docs/SPEC.md` |
| **Deployment** | `/srv/vhosts/daybreak.silverday.de/` → `https://daybreak.silverday.de` (Apache docroot: `public/`) |
| **Version** | 0.2 (decisions settled) |
| **Status** | Decisions §4 settled — ready to scaffold |
| **Stack** | LAMP — PHP 8.3, MariaDB 10.11+, Apache 2.4, server-rendered (no SPA build step) |

---

## 1. Executive Summary

Daybreak replaces the manual morning routine of opening ~15 sites and skimming for new headlines. It aggregates headlines (and feed-provided summaries where available) from a curated, admin-managed list of security/privacy sources, plus a live ransomware-activity view from ransomlook.io, and presents them in a single attractive, responsive, easy-to-read interface.

A **public page** is usable with no account and shows a default curated set. **Registered users** can customise which sources they see, suggest new ones, and filter to "since my last visit" or "last *X* days". **Admins** manage the source catalogue (add / approve / enable / disable / remove) and moderate user suggestions.

Every item links directly to the original article (opens in a new tab) and carries proper source attribution. No full-article content is stored or republished — only headline, feed-supplied summary snippet, link, and attribution.

---

## 2. Goals (MVP Scope)

1. Aggregate headlines + optional short summaries from the configured source list (the 28-source reading list) on a scheduled basis.
2. Integrate ransomlook.io's recent-posts view as a first-class source with correct CC BY 4.0 attribution.
3. Public, no-login reading page with a sensible default source selection.
4. Full user account management: register, email verify, login, change password, forgotten-password reset, account deletion.
5. Per-user customisation: select/mute sources, set default time window, "since last visit" view, "last *X* days" view.
6. User source **suggestions** (suggest, not self-add) with an admin moderation queue.
7. Admin panel: source CRUD + approve/enable/disable/remove, suggestion moderation, feed-health monitoring, basic user administration.
8. Correct attribution + new-tab outbound links on every item.
9. Responsive, accessible, attractive UI in the SilverDay palette (slate/silver — see §11.2).
10. Security-hardened throughout (NIST SP 800-63B auth baseline, SSRF-safe fetching, CSRF, XSS-safe rendering of third-party content).

## 2a. Non-Goals (explicitly out of MVP)

- No full-text article storage, no reader-mode reproduction of articles (legal + scope).
- No mobile native app (responsive web only).
- No AI-generated summaries in MVP (see §18, optional later layer).
- No social features (sharing, comments, upvotes).
- No paid tiers / billing.
- No multi-tenancy — single instance, single source catalogue (unlike LoreBuilder/Wanyanka).

---

## 3. Architecture Overview

Classic SilverDay front-controller LAMP, **server-rendered**:

```
Browser
  │  (HTML over HTTPS, minimal JS for progressive enhancement)
  ▼
Apache 2.4  ──►  public/index.php  (single front controller)
                      │
                 Router ──► Middleware (auth, CSRF, RBAC, rate-limit, security headers)
                      │
                 Controllers ──► Services ──► Repositories (PDO, prepared statements)
                      │
                 Templates (PHP partials)  ◄── render HTML
                      ▼
                 MariaDB 10.11 (InnoDB, utf8mb4)

CLI (cron):  bin/fetch.php  ──► AggregationService ──► SourceAdapter[] ──► Repositories
```

Two entry points: the web front controller (`public/index.php`) and a CLI fetch runner (`bin/fetch.php`, invoked by cron). The web layer **never** fetches feeds synchronously — it only reads the cached `articles` table.

---

## 4. Decisions (settled)

| # | Decision | Resolution |
|---|----------|------------|
| D1 | **Frontend approach** | **Server-rendered PHP** + thin progressive-enhancement JS (vanilla / optional Alpine.js, no Vite/build step). No SPA. |
| D2 | **NIST NVD handling** | **Separate CVE widget**, not the article stream — own adapter + own rendering panel. |
| D3 | **ransomlook API** | **Use the public ransomlook API.** Probed live: `/api/posts` and `/api/recent` return HTTP 200 with **no API key required**. Primary endpoint = `/api/recent` (gives victim title, group, timestamp, link). Render **text-only**; do **not** embed the `screen` screenshot paths (hotlinks leak-site content, potentially sensitive). CC BY 4.0 attribution mandatory. |
| D4 | **Email transport** | **SMTP configured in `.env`** (transactional relay); fallback to PHP `mail()` if SMTP not configured. |
| D5 | **Email verification** | **ON** — account is `pending` until the emailed verification link is confirmed; only then `active`. |
| D6 | **Public default source set** | **All `active` sources** shown to anonymous visitors. (No per-source "default" flag needed.) |
| D7 | **Hosting region** | **EU** — DPA available with provider. |

---

## 5. Tech Stack

- **Backend:** PHP 8.3+, Apache 2.4, no framework. Custom router + middleware stack. PDO + prepared statements, **no ORM**.
- **Database:** MariaDB 10.11+, InnoDB, `utf8mb4_unicode_ci`.
- **Frontend (per D1):** Server-rendered PHP templates + Tailwind via CDN/standalone CLI (no Node build pipeline) **or** a single hand-written CSS file. Minimal vanilla JS / optional Alpine.js for "load more", "mark all read", filter toggles.
- **Dependencies (kept minimal):**
  - Feed parsing: hand-rolled around `ext-libxml`/`SimpleXML` for RSS/Atom, plus `ext-json` + `ext-curl` for JSON-API sources. (SimplePie is an option but adds dependency surface — evaluate vs. a thin custom parser.)
  - HTML sanitisation for summaries: a vetted sanitiser (e.g. HTML Purifier) **or** a strict allowlist strip — non-negotiable, see §13.
- **Infra:** Ubuntu LTS, HTTPS mandatory, secrets outside webroot, separate MariaDB users per privilege level.
- **No** Composer-heavy footprint; pull only what's justified.

---

## 6. Functional Modules

### 6.1 Public News Page (anonymous)
- Renders **all `active` sources** (D6), newest-first, grouped or filterable by **category** (§6.9).
- Each item: source badge + attribution, headline (outbound link, `target="_blank" rel="noopener noreferrer nofollow"`), feed summary snippet (sanitised, truncated), relative timestamp.
- ransomlook recent-activity panel (group → victim → timestamp), **text-only**, with persistent CC BY 4.0 attribution (D3).
- NVD CVE widget rendered separately from the article stream (D2).
- Time-window control: default "last 24h"; selectable last *X* days.
- "Sign in to customise" call-to-action. No personalisation, no "since last visit" for anonymous.
- Footer present on every page with links to **Imprint**, **Terms**, **Privacy** (§6.10).

### 6.2 Aggregation Engine (`bin/fetch.php`, cron)
- Iterates enabled sources whose `next_fetch_at` is due; staggered to avoid thundering-herd.
- Per source: resolve adapter → fetch (with ETag/If-Modified-Since) → parse → normalise → upsert new articles → update fetch-health fields → write `fetch_log` row.
- Conditional GET (ETag / `Last-Modified`) stored per source to minimise bandwidth and be a good netizen.
- Backoff + auto-disable: after *N* consecutive failures, set source `degraded`, then `auto_disabled`, and flag for admin.
- Concurrency lock (per-source or global) to prevent overlapping runs.
- Idempotent upsert keyed on `(source_id, guid)`.

### 6.3 Source Adapter Layer (see §8)
- Common `SourceAdapter` interface; concrete adapters: `RssAtomAdapter`, `JsonApiAdapter`, `RansomlookAdapter`, `NvdAdapter`, and a fallback `HtmlScrapeAdapter` (last resort, per-source rules).
- Each adapter returns a normalised `NormalizedItem[]` (guid, title, url, summary, published_at).

### 6.4 Deduplication & Normalisation
- Same story often appears across BleepingComputer / THN / etc. MVP approach: compute a `dedup_key` from a normalised title (lowercase, strip punctuation/stopwords) + canonicalised URL host/path. Cluster items sharing a key into a `dedup_group`; show the primary, collapse the rest behind a "also covered by N sources" affordance.
- Keep it cheap and deterministic for MVP; semantic dedup is post-MVP.

### 6.5 User Accounts & Authentication
- Register (email + password + display name). **Email verification is mandatory (D5):** account is created `pending`, a single-use verification link is emailed, and the account becomes `active` only on confirmation. Unverified accounts cannot log in; stale `pending` accounts + expired tokens are pruned.
- Login, logout. Change password (requires current password), forgotten-password reset (emailed single-use token).
- Account deletion (DSGVO erasure) + data export (DSGVO portability) — see §13.
- DB-backed sessions; Argon2id hashing; login throttling; generic responses to prevent user enumeration.

### 6.6 User Customisation
- Source selection: opt-in/opt-out per source from the active catalogue (`user_sources`).
- Default time window (`default_window_days`).
- **"Since last visit"** view: uses `users.last_seen_at`; on viewing the personalised feed, show items `published_at > last_seen_at` with an unread count, then advance `last_seen_at`.
- **"Last X days"** view: explicit window override.
- Optional read-tracking (mark individual items read) — flagged optional; "since last visit" + window covers the stated requirement without per-item state.

### 6.7 Source Suggestions (user → admin)
- Users **suggest** a source (name, homepage URL, optional feed URL, note).
- On submit: server-side validation + a **safe probe** of the URL (SSRF-guarded, §13) to detect a parseable feed and pre-fill adapter type for the admin.
- Enters `source_suggestions` as `pending`. Admin approves (→ creates `sources` row) / rejects (with note). Suggester optionally notified.

### 6.8 Admin Panel
- **Source management:** create, edit, approve, enable, disable, remove; set category, adapter type, fetch interval, attribution text/license.
- **Suggestion moderation:** review queue, approve→create source, reject→note.
- **Feed-health dashboard:** per source — last success, last error, consecutive failures, items/day, status (active/degraded/auto_disabled). Manual "fetch now" + "reset failures".
- **User administration:** list, disable/enable, set role, delete; view audit log.
- **Audit log viewer.**

### 6.9 Categories
- Sources carry one primary **category**, mirroring your daily-briefing taxonomy so the UI matches how you already think:
  `Critical / Patch Now` · `Threat Intel` · `Strategic` · `DACH Corner` · `Privacy` · `Ransomware` · `EU / Policy`.
- Users and the public page can filter by category. (Initial mapping in Appendix A.)

### 6.10 Legal & Static Pages
- **Imprint (Impressum):** legally required for a German operator — operator identity, contact, responsible person.
- **Terms of Service (TOS / Nutzungsbedingungen):** usage terms, account rules, disclaimer, third-party content notice.
- **Privacy Policy (Datenschutzerklärung):** DSGVO Art. 13/14 disclosures — data collected (email, hash, display name, preferences), purposes, legal basis, retention, subject rights, processor/DPA, no tracking.
- Rendered as static server-side templates; linked from the global footer on every page. Content is operator-supplied (placeholders in MVP; final legal text out of scope for the build).

---

## 7. Roles & RBAC

| Role | Capabilities |
|------|--------------|
| `anonymous` | Read public page + default sources; no personalisation. |
| `user` | All of the above + customise sources, time windows, since-last-visit, suggest sources, manage own account. |
| `admin` | All of the above + source catalogue CRUD/approve/enable/disable, suggestion moderation, feed-health, user admin, audit log. |

RBAC enforced in middleware; deny-by-default on admin routes.

---

## 8. Source Adapter Architecture

Common contract so heterogeneous sources normalise uniformly:

```php
interface SourceAdapter {
    /** @return NormalizedItem[] */
    public function fetch(Source $source, ConditionalCache $cache): FetchResult;
    public function supports(string $adapterType): bool;
}

final class NormalizedItem {
    public string $guid;        // stable per-source unique id (feed guid or hashed url)
    public string $title;
    public string $url;         // outbound article link
    public ?string $summary;    // feed-provided, sanitised, may be null
    public ?DateTimeImmutable $publishedAt;
}

final class FetchResult {
    public array $items;        // NormalizedItem[]
    public int $httpStatus;
    public ?string $etag;
    public ?string $lastModified;
    public bool $notModified;   // 304 short-circuit
}
```

Concrete adapters:
- **RssAtomAdapter** — RSS 2.0 / Atom via SimpleXML; handles both feed dialects.
- **JsonApiAdapter** — generic JSON endpoints with a configurable field map.
- **RansomlookAdapter** — hits `https://www.ransomlook.io/api/recent` (no API key required — confirmed live); maps `post_title` (victim) / `group_name` / `discovered` timestamp / relative `link`. Text-only; ignores `screen`/`magnet`. Injects CC BY 4.0 attribution.
- **NvdAdapter** — NVD CVE API → rendered as the CVE widget (D2), not the article stream.
- **HtmlScrapeAdapter** — last-resort per-source DOM extraction; isolated, conservative, used only where no feed exists.

Adapter type is stored on the source so the engine resolves the right one. New non-uniform sources slot in without touching the engine.

---

## 9. Database Schema (MariaDB, InnoDB, utf8mb4)

> Abbreviated DDL — types/indices indicative; full migrations to be generated in Phase 0.

```sql
-- Users & auth ------------------------------------------------------------
CREATE TABLE users (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  email           VARCHAR(254) NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,          -- Argon2id
  display_name    VARCHAR(80)  NOT NULL,
  role            ENUM('user','admin') NOT NULL DEFAULT 'user',
  status          ENUM('pending','active','disabled') NOT NULL DEFAULT 'pending',
  default_window_days TINYINT UNSIGNED NOT NULL DEFAULT 1,
  email_verified_at DATETIME NULL,
  last_login_at   DATETIME NULL,
  last_seen_at    DATETIME NULL,                  -- drives "since last visit"
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE auth_tokens (                         -- reset + verification (hashed)
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,
  type        ENUM('password_reset','email_verify') NOT NULL,
  token_hash  CHAR(64) NOT NULL,                   -- SHA-256 of random token
  expires_at  DATETIME NOT NULL,
  used_at     DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (token_hash),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE sessions (                            -- DB-backed sessions
  id            CHAR(64) PRIMARY KEY,              -- random session id
  user_id       BIGINT UNSIGNED NULL,
  ip_hash       CHAR(64) NULL,
  user_agent    VARCHAR(255) NULL,
  payload       MEDIUMTEXT NOT NULL,
  last_activity DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE login_attempts (                      -- throttling
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  email       VARCHAR(254) NULL,
  ip_hash     CHAR(64) NOT NULL,
  successful  TINYINT(1) NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (ip_hash, created_at), KEY (email, created_at)
);

-- Sources & catalogue -----------------------------------------------------
CREATE TABLE source_categories (
  id         INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name       VARCHAR(64) NOT NULL,
  slug       VARCHAR(64) NOT NULL UNIQUE,
  color      VARCHAR(7)  NULL,                     -- palette hex
  sort_order INT NOT NULL DEFAULT 0
);

CREATE TABLE sources (
  id               INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name             VARCHAR(120) NOT NULL,
  slug             VARCHAR(120) NOT NULL UNIQUE,
  homepage_url     VARCHAR(500) NOT NULL,
  feed_url         VARCHAR(500) NULL,
  adapter_type     ENUM('rss_atom','json_api','ransomlook','nvd','html_scrape') NOT NULL,
  field_map        JSON NULL,                      -- for json_api adapters
  category_id      INT UNSIGNED NULL,
  attribution_text VARCHAR(255) NOT NULL,
  license          VARCHAR(120) NULL,              -- e.g. 'CC BY 4.0'
  status           ENUM('pending','active','disabled','degraded','auto_disabled') NOT NULL DEFAULT 'pending',
  fetch_interval_min SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  etag             VARCHAR(255) NULL,
  last_modified_hdr VARCHAR(64) NULL,
  next_fetch_at    DATETIME NULL,
  last_fetch_at    DATETIME NULL,
  last_success_at  DATETIME NULL,
  last_error       VARCHAR(500) NULL,
  consecutive_failures SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_by       BIGINT UNSIGNED NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES source_categories(id) ON DELETE SET NULL
);

-- Articles ----------------------------------------------------------------
CREATE TABLE articles (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  source_id     INT UNSIGNED NOT NULL,
  guid          VARCHAR(500) NOT NULL,             -- per-source stable id
  title         VARCHAR(500) NOT NULL,
  url           VARCHAR(1000) NOT NULL,
  summary       TEXT NULL,                         -- sanitised feed snippet
  published_at  DATETIME NULL,
  fetched_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  dedup_key     CHAR(40) NULL,                     -- normalised-title hash
  UNIQUE KEY uq_source_guid (source_id, guid),
  KEY idx_published (published_at),
  KEY idx_dedup (dedup_key),
  FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE CASCADE
);

-- Per-user customisation --------------------------------------------------
CREATE TABLE user_sources (
  user_id   BIGINT UNSIGNED NOT NULL,
  source_id INT UNSIGNED NOT NULL,
  enabled   TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (user_id, source_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE CASCADE
);

-- Suggestions -------------------------------------------------------------
CREATE TABLE source_suggestions (
  id               BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  suggested_by     BIGINT UNSIGNED NULL,
  name             VARCHAR(120) NOT NULL,
  homepage_url     VARCHAR(500) NOT NULL,
  feed_url         VARCHAR(500) NULL,
  detected_adapter VARCHAR(32) NULL,               -- from safe probe
  note             VARCHAR(500) NULL,
  status           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by      BIGINT UNSIGNED NULL,
  reviewed_at      DATETIME NULL,
  review_note      VARCHAR(500) NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (suggested_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Operations / observability ---------------------------------------------
CREATE TABLE fetch_log (
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  source_id   INT UNSIGNED NOT NULL,
  status      ENUM('ok','not_modified','error') NOT NULL,
  http_status SMALLINT UNSIGNED NULL,
  items_found SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  items_new   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  duration_ms INT UNSIGNED NULL,
  error       VARCHAR(500) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (source_id, created_at),
  FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE CASCADE
);

CREATE TABLE audit_log (
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NULL,
  action      VARCHAR(80) NOT NULL,
  target_type VARCHAR(40) NULL,
  target_id   VARCHAR(64) NULL,
  ip_hash     CHAR(64) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (user_id, created_at)
);
```

Retention: `articles` pruned after a configurable window (e.g. 90 days); `fetch_log`/`login_attempts` rotated; `audit_log` kept longer.

---

## 10. Routing / Endpoint Map

Server-rendered page routes + a small JSON layer for progressive enhancement and the CLI fetcher.

**Public**
- `GET  /` — public news page (all active sources, time-window control)
- `GET  /category/{slug}` — filtered public view
- `GET  /about` — about + attribution/licenses
- `GET  /imprint` — Impressum
- `GET  /terms` — Terms of Service
- `GET  /privacy` — Privacy Policy (Datenschutzerklärung)

**Auth**
- `GET/POST /register`
- `GET  /verify/{token}` — email verification
- `GET/POST /login`
- `POST /logout`
- `GET/POST /password/forgot`
- `GET/POST /password/reset/{token}`

**User (auth required)**
- `GET  /feed` — personalised feed (since-last-visit / last-X-days)
- `GET/POST /settings/sources` — select/mute sources
- `GET/POST /settings/account` — display name, default window, change password
- `POST /settings/account/delete` — DSGVO erasure
- `GET  /settings/export` — DSGVO data export
- `GET/POST /suggest` — submit a source suggestion

**Admin (admin role)**
- `GET  /admin` — dashboard (feed health summary)
- `GET/POST /admin/sources` — list/create
- `GET/POST /admin/sources/{id}` — edit/enable/disable/remove
- `POST /admin/sources/{id}/fetch` — fetch now
- `GET  /admin/suggestions` — moderation queue
- `POST /admin/suggestions/{id}` — approve/reject
- `GET/POST /admin/users` — user admin
- `GET  /admin/audit` — audit log

**Internal JSON (progressive enhancement)**
- `GET  /api/articles?window=&category=&before=` — paginated load-more
- `POST /api/seen` — advance `last_seen_at`

**CLI**
- `php bin/fetch.php [--source=slug] [--force]` — cron entry point

---

## 11. Frontend / UX

### 11.0 Layout structure

Two zones: a **primary column** carrying the main news feed, and a **widget rail** holding the ransomlook and CVE widgets. The feed is the focus; the widgets are secondary, glanceable panels.

```
Desktop (≥1024px)                          Mobile (<768px)
┌───────────────────────┬──────────────┐   ┌──────────────────────┐
│  Filter bar (sticky)   │              │   │  Filter bar (sticky) │
│  category chips · window│  WIDGET RAIL │   ├──────────────────────┤
├───────────────────────┤              │   │   MAIN NEWS FEED      │
│                       │ ┌──────────┐ │   │   (full width)       │
│   MAIN NEWS FEED       │ │ Ransom-  │ │   │   …                  │
│   (primary column)     │ │ look     │ │   ├──────────────────────┤
│   • headline + source  │ │ widget   │ │   │  Ransomlook widget   │
│   • summary snippet    │ └──────────┘ │   │  (collapsible)       │
│   • timestamp          │ ┌──────────┐ │   ├──────────────────────┤
│   • [load more]        │ │ CVE/NVD  │ │   │  CVE / NVD widget    │
│                       │ │ widget   │ │   │  (collapsible)       │
│                       │ └──────────┘ │   └──────────────────────┘
└───────────────────────┴──────────────┘
```

- **Main news feed (primary):** the aggregated articles, newest-first, the dominant element.
- **Ransomlook widget:** compact recent-activity list (group → victim → time), text-only, fed from `/api/recent`, with persistent "Data: ransomlook.io (CC BY 4.0)" attribution.
- **CVE / NVD widget:** latest CVEs from the NVD adapter (id, severity, short title, link), rendered separately from the article stream (D2).
- **Responsive:** desktop shows feed + right-hand widget rail; below the breakpoint the rail drops beneath the feed and the two widgets become collapsible panels so the feed stays the priority on small screens.

### 11.1 Visual / interaction details

- **Feed items:** dense but readable; source badge + attribution, headline (outbound link, new tab), sanitised summary snippet, relative timestamp; unread indicator for logged-in users.
- **Filter bar:** sticky; category chips + time-window selector ("last 24h" / last *X* days / since last visit).
- **Palette:** SilverDay slate/silver scheme (see §11.2); functional state colours (green/red/dark-blue) for source health and category coding.
- **Accessibility:** semantic HTML, WCAG AA contrast, keyboard navigable, `prefers-reduced-motion` respected, proper `lang` attributes (DE/EN content mix).
- **Performance:** server-rendered first paint; "load more" via the JSON endpoint; widgets can refresh independently; no heavy client framework.

### 11.2 Design Tokens (from live silverday.media stylesheet)

Reuse the existing SilverDay CSS custom properties for visual consistency:

```css
:root {
  --color-bg:             #ffffff;  /* background */
  --color-card-bg:        #f8f9fa;  /* cards / panels */
  --color-text:           #1a1a1a;  /* primary text */
  --color-text-secondary: #4a4a4a;  /* secondary text */
  --color-accent:         #2c3e50;  /* primary accent (dark slate) */
  --color-accent-light:   #3498db;  /* secondary accent (bright blue) */
  --color-border:         #e0e0e0;  /* borders */
  /* neutral grey ramp also in use: #333, #909090, #a0a0a0, #c0c0c0, #000 */

  /* Daybreak functional extensions (Klaus-approved additions) */
  --color-success:        /* dark green — healthy source */;
  --color-danger:         /* red — error / auto-disabled / critical */;
  --color-accent-dark:    /* dark blue — third accent for category coding */;
}
```

> Exact hex values for the three functional extensions to be chosen against the AA-contrast requirement in §11; the base seven are taken verbatim from the live site.

---

## 12. Scheduling & Operations

- **Cron:** `*/5 * * * * php /srv/vhosts/daybreak.silverday.de/bin/fetch.php` — runner picks only due sources (`next_fetch_at <= now`), honouring per-source `fetch_interval_min`.
- Staggered scheduling, global lock, per-source timeout (e.g. 10 s), max response size cap.
- Conditional GET via stored `etag` / `last_modified_hdr`.
- Failure handling: increment `consecutive_failures`; at threshold → `degraded`; higher threshold → `auto_disabled` + admin flag. Reset on success.
- Article pruning + log rotation via a daily maintenance task.

---

## 13. Security Requirements (non-negotiable)

**Authentication (NIST SP 800-63B baseline)**
- Argon2id password hashing; min length ≥ 12; screen against breached-password lists; no composition rules / forced rotation.
- Login throttling (per-IP + per-account); generic error messages (no user enumeration on login, register, or reset).
- Password-reset and email-verify tokens: cryptographically random, stored only as SHA-256 hashes, single-use, short TTL.
- DB-backed sessions; cookies `Secure; HttpOnly; SameSite=Lax`; session regeneration on privilege change; idle + absolute timeout.

**SSRF — critical (feed fetcher + suggestion probe accept user/admin-supplied URLs)**
- Allowlist schemes `http`/`https` only; reject credentials in URL.
- Resolve DNS and **block private/reserved/link-local ranges and cloud metadata endpoints** (169.254.169.254 etc.) before connecting; re-validate after redirects; cap redirect depth.
- Enforce timeouts and response-size limits; no arbitrary protocols.

**XSS / third-party content**
- All feed-supplied titles/summaries are untrusted: **sanitise** (allowlist) before storage and **escape** on output. Strip scripts/styles/event handlers/iframes.
- Strict Content-Security-Policy; `X-Content-Type-Options: nosniff`; `Referrer-Policy`; `X-Frame-Options`/frame-ancestors.

**General**
- CSRF tokens on all state-changing requests.
- Input validation whitelist; parameterised queries only (PDO prepared statements).
- Secrets outside webroot; separate MariaDB users (app vs. migration vs. read-only) with least privilege.
- Outbound article links: `rel="noopener noreferrer nofollow" target="_blank"`.
- Audit logging of auth events, admin actions, source/suggestion changes.

---

## 14. Privacy, Legal & Attribution (DSGVO)

- **Data minimisation:** only email, password hash, display name, and preferences. No tracking, no third-party analytics → only strictly-necessary cookies (session/CSRF), so likely no consent banner required.
- **Subject rights:** self-service account deletion (erasure) and data export (portability); password reset via email.
- **Hosting:** EU (D7); DPA available with provider; documented TOMs.
- **Content/copyright:** store and display **only** headline + feed-provided summary snippet + link + attribution. No full-article reproduction. Every item attributes its source and links to the original. Respect `robots.txt`/feed ToS; `nofollow` on outbound links.
- **ransomlook.io:** content is CC BY 4.0 → persistent, visible attribution to ransomlook.io wherever its data appears.
- **Privacy policy + imprint (Impressum):** required (German operator).

---

## 15. Project Structure

Deployment root: `/srv/vhosts/daybreak.silverday.de/`. Apache `DocumentRoot` points at `public/`; everything else (incl. `config/.env`, `storage/`, `src/`) sits **above** the docroot and is not web-served.

```
/srv/vhosts/daybreak.silverday.de/
├── public/                 # Apache DocumentRoot — index.php only + static assets
│   ├── index.php           # front controller
│   └── assets/             # css, minimal js, icons
├── src/
│   ├── Config/
│   ├── Router/
│   ├── Middleware/         # Auth, Csrf, Rbac, RateLimit, SecurityHeaders
│   ├── Controller/         # Public, Auth, User, Admin
│   ├── Service/            # Aggregation, Dedup, Mail, Suggestion, Auth
│   ├── Adapter/            # RssAtom, JsonApi, Ransomlook, Nvd, HtmlScrape
│   ├── Repository/         # PDO data access, one per aggregate
│   ├── Model/
│   ├── Security/           # Argon2id, tokens, SSRF guard, sanitiser
│   └── View/               # PHP templates + partials
├── bin/
│   ├── fetch.php           # cron entry point
│   └── maintenance.php     # pruning / rotation
├── migrations/             # numbered SQL (001_..., 002_...)
├── storage/                # cache, logs (outside docroot)
├── config/                 # env loader (.env outside webroot)
├── tests/
├── docs/
│   └── SPEC.md             # this document
└── CLAUDE.md               # Claude Code project context
```

---

## 16. Configuration

`.env` (outside webroot, e.g. `/srv/vhosts/daybreak.silverday.de/config/.env`): DB DSN/credentials (least-priv app user), app key/secret, base URL (`https://daybreak.silverday.de`), **SMTP settings (host/port/user/pass/TLS); if unset, the MailService falls back to PHP `mail()` (D4)**, ransomlook API base (`https://www.ransomlook.io/api`, no key required — D3), fetch concurrency/timeout caps, article retention days, throttling thresholds.

---

## 17. Phased Implementation Plan

| Phase | Deliverable |
|-------|-------------|
| **0 — Foundation** | Scaffold, front controller + router + middleware, config/env, migrations 001–00n, security baseline (CSRF, headers, sessions), CLAUDE.md. |
| **1 — Aggregation + public page** | Adapter layer (RSS/Atom, JSON, ransomlook, NVD), `bin/fetch.php` + cron, conditional GET, articles upsert, public page with categories + time window, attribution, outbound links. |
| **2 — Accounts & auth** | Register, email verify, login/logout, change password, forgotten-password reset, DB sessions, throttling, account deletion/export. |
| **3 — Customisation** | Source select/mute, default window, since-last-visit, last-X-days, personalised `/feed`. |
| **4 — Suggestions + admin** | Suggestion submission + safe probe, admin source CRUD/approve/enable/disable/remove, suggestion moderation, feed-health dashboard, user admin, audit viewer. |
| **5 — Dedup + polish + hardening** | Dedup clustering, responsive/accessibility pass, SSRF/XSS review, pruning/maintenance, seed catalogue load. |

---

## 18. Post-MVP / Future

- Optional AI summaries (server-side, your usual proxied-key pattern) for sources with thin feed snippets.
- OPML import/export of source lists.
- Email/RSS digest of the personalised feed (your "morning briefing" format: Critical/Patch Now → Threat Intel → Strategic → DACH Corner).
- Saved searches / keyword alerts.
- Semantic dedup + clustering.
- Multi-language UI (DE/EN) toggle.

---

## Appendix A — Initial Source Catalogue (seed)

> **Feed URLs verified by live HTTP probe on 2026-06-11.** Status legend: **✅ live** (HTTP 200, valid feed) · **⚠️ blocked/needs-handling** (correct URL but the host returned a bot/error status to an automated request from a datacenter IP — see note below) · **⏸ deferred** (owner adds via admin GUI) · **❌ excluded** (no usable feed).
>
> **Seed summary:** 23 sources go into the initial seed — **18 ✅ live** (16 RSS + NVD JSON widget + ransomlook JSON) and **5 ⚠️ live-but-bot-blocked** (need fetch-handling). **2 deferred** to the GUI (IAPP, BSI). **2 excluded** for having no feed (ENISA, tl;dr sec). **2 dropped** at owner's request (Cybersec Café, Medium).

| Source | Category | Adapter | Verified feed URL | Status |
|--------|----------|---------|-------------------|--------|
| BleepingComputer | Strategic | rss_atom | `https://www.bleepingcomputer.com/feed/` | ✅ |
| The Hacker News | Strategic | rss_atom | `https://feeds.feedburner.com/TheHackersNews` | ✅ |
| Krebs on Security | Strategic | rss_atom | `https://krebsonsecurity.com/feed/` | ✅ |
| Schneier on Security | Strategic | rss_atom | `https://www.schneier.com/feed/atom/` | ✅ |
| Dark Reading | Strategic | rss_atom | `https://www.darkreading.com/rss.xml` | ✅ |
| SecurityWeek | Strategic | rss_atom | `https://www.securityweek.com/feed/` | ✅ |
| The Record | Threat Intel | rss_atom | `https://therecord.media/feed/` | ✅ |
| The Register (Security) | Strategic | rss_atom | `https://www.theregister.com/security/headlines.atom` | ✅ |
| heise online (Security) | DACH Corner | rss_atom | `https://www.heise.de/security/rss/news-atom.xml` (→ `…/security/feed.xml`) | ✅ |
| Dr. Datenschutz | Privacy / DACH | rss_atom | `https://www.dr-datenschutz.de/feed/` | ✅ |
| OpenSSF Blog | Threat Intel | rss_atom | `https://openssf.org/feed/` | ✅ |
| CISA Advisories | Critical / Patch Now | rss_atom | `https://www.cisa.gov/cybersecurity-advisories/all.xml` | ✅ |
| Mandiant / Google Threat Intel | Threat Intel | rss_atom | `https://cloudblog.withgoogle.com/topics/threat-intelligence/rss/` | ✅ |
| Microsoft Threat Intel / Security | Threat Intel | rss_atom | `https://www.microsoft.com/en-us/security/blog/feed/` | ✅ |
| Talos Intelligence | Threat Intel | rss_atom | `https://blog.talosintelligence.com/rss/` | ✅ |
| Risky Business (News) | Threat Intel | rss_atom | `https://news.risky.biz/rss/` (also `risky.biz/feeds/risky-business-news/`) | ✅ |
| NIST NVD | Critical / Patch Now | nvd (CVE widget) | `https://services.nvd.nist.gov/rest/json/cves/2.0` | ✅ JSON |
| BSI / CERT-Bund (WID) | DACH Corner | rss_atom | `https://wid.cert-bund.de/content/public/securityAdvisory/rss` | ✅ |
| BSI / CERT-Bund (WID) | DACH Corner | rss_atom | `https://wid.cert-bund.de/content/public/securityAdvisory/rss` | ⏸ deferred — owner adds via GUI |
| CyberSecurityNews | Strategic | rss_atom | `https://cybersecuritynews.com/feed/` | ⚠️ 202 (Cloudflare) |
| Cybernews | Strategic | rss_atom | `https://cybernews.com/feed/` | ⚠️ 403 |
| Exploit-DB | Critical / Patch Now | rss_atom | `https://www.exploit-db.com/rss.xml` | ⚠️ 502 (Cloudflare) |
| Sophos News | Threat Intel | rss_atom | `https://news.sophos.com/en-us/feed/` | ⚠️ 503 |
| Datenschutz-Guru | Privacy / DACH | rss_atom | `https://www.datenschutz-guru.de/feed/` | ⚠️ 500 (server error) |
| IAPP | Privacy / Policy | rss_atom | `https://iapp.org/rss/daily-dashboard` (published) | ⏸ deferred — owner adds via GUI |
| ENISA | EU / Policy | — | *RSS discontinued with new site; no public feed live* | ❌ excluded |
| tl;dr sec | Threat Intel | — | *no feed at `/rss/` or `/feed/`; primarily email newsletter* | ❌ excluded |
| **ransomlook.io** | Ransomware | ransomlook | `https://www.ransomlook.io/api/recent` (JSON, no key, CC BY 4.0) | ✅ JSON |

**Notes & exceptions**

1. **⚠️ Bot/datacenter blocking (operational risk):** CyberSecurityNews, Cybernews, Exploit-DB, Sophos and Datenschutz-Guru returned bot/error statuses (202/403/500/502/503) to automated requests from this sandbox's datacenter IP. The URLs are correct; the hosts (mostly Cloudflare-fronted) block non-browser clients. The **production fetcher will likely hit the same wall**, so the AggregationService must send a realistic User-Agent and handle these gracefully; some hosts may still block server IPs and could need a fetch proxy or a longer retry/backoff. This is a real risk for ~5 sources — flagged, not yet solved.
2. **⏸ Deferred (owner adds via admin GUI):** **IAPP** and **BSI** are intentionally left out of the automatic seed — Klaus will confirm the exact feed (for IAPP, whether the Daily Dashboard feed is reachable; for BSI, press feed vs. the CERT-Bund WID advisory feed shown above) and add them through source management.
3. **❌ Excluded — no usable feed:** **ENISA** (RSS discontinued with their new site; no replacement live) and **tl;dr sec** (no feed at standard paths; email-first). Both would require an `html_scrape` adapter or a manual source later; not seeded.
4. **Dropped from the list:** **Cybersec Café** (irregular/uncertain posting cadence) and **Medium** (no single feed; per-publication only) — removed at owner's request.

---

*End of specification v0.2 — decisions §4 settled; Appendix A feed URLs verified 2026-06-11.*
