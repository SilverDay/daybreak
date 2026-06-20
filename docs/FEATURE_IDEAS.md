# Daybreak — Feature Ideas

New ideas not already covered in `FEATURE_BACKLOG.md` or `feature-design-improvement.md`.
Ordered roughly by value-to-effort ratio for a single-user self-hosted instance.

---

## 1. EPSS Scores on CVE Entries

**What:** Fetch and store the EPSS (Exploit Prediction Scoring System) probability score
alongside each CVE from CISA KEV and display it in the widget.

**Why:** CVSS severity tells you how bad a vuln is in theory. EPSS tells you the
probability it will actually be exploited in the next 30 days. For a security practitioner
this is a far better triage signal. CISA KEV entries are already "known exploited", but
EPSS adds a quantitative likelihood score useful for prioritising patching.

**Source:** `https://api.first.org/data/v1/epss?cve=CVE-YYYY-NNNNN` (free, no key required).

**Implementation sketch:**
- After `CisaKevAdapter` upserts CVEs, a second pass queries the FIRST EPSS API for each new CVE ID.
- Store the score (float 0–1) in a new `cve_epss` table keyed on CVE ID with a `fetched_at`.
- Widget query JOINs on `cve_epss` and renders the probability as a percentage alongside the severity chip.
- Refresh scores weekly (they update daily but weekly is enough for widget use).

**Files:** `src/Adapter/CisaKevAdapter.php`, new `src/Service/EpssService.php`, new migration, `src/View/layout_end.php`.

---

## 2. Keyword Watch Terms / Highlights

**What:** Users define a list of watch terms (e.g. "zero-day", "Cisco", "ransomware",
"Exchange"). Articles containing those terms in the title or summary are visually
highlighted in My Feed and optionally surfaced in a dedicated "Alerts" section at the top.

**Why:** A security professional monitoring 30+ sources doesn't want to read every headline.
Watch terms let the feed surface what matters without requiring a separate search.

**Implementation sketch:**
- New `user_watch_terms` table: `(id, user_id, term, created_at)`.
- New settings sub-page `/settings/alerts` (fourth tab in the settings subnav).
- `FeedController` fetches the user's watch terms; any article whose title or summary
  matches a term gets a `highlight` flag in the template.
- Terms matching on `published_at` within the last 24 h are pulled to an "Alerts" banner
  at the top of My Feed before the regular article list.
- Match logic: `LIKE '%term%'` or `REGEXP` in the feed query, or PHP-side filtering on the
  already-fetched article set (simpler, safe at 60-article LIMIT).

**Files:** new migration, `src/Controller/UserController.php`, `src/Controller/FeedController.php`, new settings view, CSS highlight token.

---

## 3. Personal RSS Output URL

**What:** Authenticated users can generate a personal RSS feed URL
(`/feed/rss?token=<secret>`) that serves their personalised article list as a valid
RSS 2.0 document, consumable in any external reader (Reeder, NetNewsWire, etc.).

**Why:** Some users check feeds in a dedicated RSS reader. This lets Daybreak act as the
aggregation/filtering backend while preserving choice of reading client.

**Security notes:**
- Token stored as SHA-256 hash (like reset tokens); raw value shown once on generation.
- No session required — authentication is via the URL token (bearer-style).
- Token can be revoked from `/settings/account`.
- Feed document is `noindex` and served with `Cache-Control: private`.

**Implementation sketch:**
- New `user_feed_tokens` table: `(id, user_id, token_hash, created_at)`.
- New route `GET /feed/rss` authenticated by query-param token.
- Controller applies user's source preferences and language filter, returns RSS 2.0 XML.
- `/settings/account` General tab gains a "Personal RSS feed" section with generate/revoke controls.

**Files:** new migration, `src/Controller/FeedController.php`, `public/index.php`, `src/View/user/general.php`.

---

## 4. Native Article Starring ("Save for later")

**What:** A one-click star/bookmark within Daybreak itself — no external Kioju dependency.
Starred articles appear in a `/starred` list and survive the feed time window.

**Why:** Kioju integration is a nice power feature but requires an external account.
A native lightweight save-for-later covers the 80% case (clip something interesting to read
tonight) with zero friction and no external dependency.

**Implementation sketch:**
- New `user_starred_articles` table: `(id, user_id, article_id, created_at)`.
- Star toggle: small `POST /star` endpoint returning JSON `{starred: bool}` for inline JS toggle.
- New route `GET /starred` — paginated list of starred articles.
- Star icon rendered on each article card in My Feed; JS toggles class and fires POST.
- Articles that are deleted from `articles` via the prune job: keep the starred row, set a
  `detached` flag, and render "source no longer available" in the starred list.

**Files:** new migration, new `StarController`, `src/View/feed/personalised.php`, `public/index.php`, minor CSS + JS.

---

## 5. Webhook / Push Notifications

**What:** Users configure one or more outbound webhook URLs (Slack, Discord, Teams,
generic HTTP). When a new article matching defined criteria (watch term, category,
source) is fetched, Daybreak POSTs a brief notification to the webhook.

**Why:** The feed requires the user to pull. A webhook turns Daybreak into a push alerting
system — critical for on-call security engineers who need immediate notification of
breaking advisories without polling.

**Implementation sketch:**
- New `user_webhooks` table: `(id, user_id, url, format ENUM('slack','discord','generic'), filter_json, active, created_at)`.
- `filter_json` encodes optional criteria: `{"terms": ["CVE-2024", "critical"], "categories": ["vuln"]}`.
- New `/settings/webhooks` settings tab (5th item in subnav, or grouped under Alerts).
- After each successful `AggregationService::runSource()`, matching new articles are POSTed
  via `FeedFetcher::postForm()` (Slack/Discord use specific JSON shapes).
- Webhook delivery: synchronous in the cron run is fine at low article volume; if delivery
  fails, record it in a `webhook_log` and retry once on next cron tick.
- The SSRF guard (`SsrfGuard::assertSafe()`) **must** run on every webhook URL before delivery.

**Security note:** Webhook URLs must be validated against the existing SSRF guard on save
and again on every delivery attempt.

**Files:** new migration, `src/Service/WebhookService.php`, `src/Service/AggregationService.php`, new settings view, `public/index.php`.

---

## 6. GitHub Security Advisories Adapter

**What:** A new `github_advisory` adapter that pulls from the GitHub Advisory Database
(`https://api.github.com/advisories`) — covering ecosystem-specific CVEs for npm, PyPI,
Maven, Go, Rust, etc., that NVD/CISA often lag behind on.

**Why:** CISA KEV focuses on actively exploited vulns. GitHub Advisories covers the
long tail of library-level vulnerabilities that affect developers and DevSecOps practitioners.
It's free, no key required for public advisories (rate limits are generous with a key).

**Implementation sketch:**
- New `adapter_type = 'github_advisory'`, new migration.
- New `GitHubAdvisoryAdapter` using `FeedFetcher::get()` with `Accept: application/vnd.github+json`.
- Fetch the 20 most recently updated advisories filtered to `type=reviewed&severity=high,critical`.
- Map `ghsa_id` as guid, `summary` as title, `cve_id` in the summary, `published_at` from `published_at`.
- Optional `GITHUB_TOKEN` config key for higher rate limits (60→5000 req/h).

**Files:** new `src/Adapter/GitHubAdvisoryAdapter.php`, new migration, `src/Service/AggregationService.php`, `config/.env.example`.

---

## 7. Dark Mode

**What:** CSS `prefers-color-scheme: dark` media query support with an optional manual
toggle (stored in `localStorage`).

**Why:** Security people work odd hours. A dark mode that respects system preference and
allows manual override is table-stakes for a tool used at 2am during incident response.

**Implementation sketch:**
- Define a `[data-theme="dark"]` CSS block alongside `@media (prefers-color-scheme: dark)`.
- All colors already use CSS custom properties (if not, tokenise them first — low effort,
  high value independently).
- Toggle button in the header sets `document.documentElement.dataset.theme` and writes
  `localStorage.setItem('theme', 'dark')`.
- 5-line inline `<script>` in `<head>` reads `localStorage` before paint to avoid flash.
- No server-side state needed.

**Files:** `public/assets/css/app.css`, `public/assets/js/app.js`, `src/View/layout.php`, `src/View/admin_layout.php`, `src/View/settings_layout.php`.

---

## 8. Per-Article Read Tracking

**What:** Track which individual articles the user has opened (via link click), not just
"mark all as seen". Visited articles are visually de-emphasised in subsequent feed loads,
similar to how browser visited-link colour works but persistent across sessions/devices.

**Why:** The current "since last visit" mode shows everything fetched since the last
"Mark as seen". If the user clicked 3 of 20 articles before marking seen, the 17 unread
ones are silently discarded. Per-article tracking lets the feed keep showing unread items.

**Implementation sketch:**
- New `user_article_reads` table: `(user_id, article_id, read_at)`, PK `(user_id, article_id)`.
- Feed article links are routed through `GET /r/{article_id}` (tiny redirect endpoint) that
  records the read and redirects to the real URL. `rel="noopener noreferrer nofollow"` is preserved.
- Alternatively: a small JS `fetch()` fires a `POST /read` silently on click (no redirect needed).
- Feed controller JOINs on `user_article_reads` and passes a `read` flag to the template.
- Prune job deletes reads older than 90 days alongside article pruning.

**Files:** new migration, `src/Controller/FeedController.php`, `src/View/feed/personalised.php`, `public/assets/js/app.js`, new prune query.

---

## 9. Feed Pagination / "Load More"

**What:** Replace the hard `LIMIT 60` on My Feed with either traditional prev/next
pagination or a "Load more" button that appends the next batch.

**Why:** During a "catch up" session after a long absence (or switching to "Last 7 days")
the 60-article cap silently drops content. Pagination surfaces the full result set.

**Implementation sketch:**
- Add `?page=N` support to `FeedController::feed()` with `OFFSET` calculation.
- Keep `LIMIT 20` per page; render prev/next links at the bottom.
- "Since last visit" mode doesn't really benefit from pagination (it's bounded by
  `fetched_at`) — apply only to `?days=N` window mode.
- No JS required for basic pagination; optional JS progressive enhancement for "load more".

**Files:** `src/Controller/FeedController.php`, `src/View/feed/personalised.php`.

---

## 10. OPML Import for Admins

**What:** Admins can upload an OPML file (standard RSS reader export format) to bulk-create
pending sources instead of adding each one manually.

**Why:** Migrating a curated RSS reading list into Daybreak currently requires creating each
source by hand. OPML import turns a 30-minute task into 30 seconds.

**Implementation sketch:**
- New admin route `POST /admin/sources/import-opml`.
- PHP `SimpleXML` parses the OPML, extracts `<outline xmlUrl="..." text="...">` entries.
- For each entry: validate URL via `SsrfGuard::assertSafe()`, create a `pending` source
  with `adapter_type = 'rss_atom'` and the feed URL. Skip duplicates (existing `feed_url`).
- Admin sees a summary: "23 sources created, 4 skipped (already exist), 1 skipped (invalid URL)".
- No automatic activation — sources land in `pending` for the admin to review and enable.

**Files:** `src/Controller/AdminController.php`, `src/View/admin/sources/list.php`, `public/index.php`.

---

## Priority Recommendation

| # | Feature | Effort | Value |
|---|---------|--------|-------|
| 7 | Dark mode | Low | High | Done
| 9 | Feed pagination | Low | Medium | Done
| 2 | Keyword watch terms | Medium | High | Done 
| 3 | Personal RSS output | Medium | High | Done 
| 4 | Native starring | Medium | High | Done 
| 6 | GitHub Advisory adapter | Medium | High | Done
| 5 | Webhooks | Medium–High | High | Done
| 1 | EPSS scores | Medium | Medium | Done
| 8 | Per-article read tracking | Medium | Medium |
| 10 | OPML import | Low | Medium |

**Suggested starting order:** Dark mode → GitHub Advisory adapter → Keyword watch terms → Personal RSS output.
Dark mode is the fastest win. GitHub Advisory fills a real gap in source coverage.
Watch terms + personal RSS together make Daybreak genuinely proactive rather than passive.
