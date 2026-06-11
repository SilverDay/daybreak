# Daybreak — Implementation Plan

Build **phase by phase**. Each phase lists tasks and **acceptance criteria** (AC) that
must pass before moving on. Run `/security-audit` before closing any phase touching auth,
fetching, or output. Full detail in `docs/SPEC.md` (section refs below).

---

## Phase 0 — Foundation  *(scaffolded in this package)*

**Goal:** a routable, secure skeleton with the schema applied.

Tasks
- [x] Directory tree, `CLAUDE.md`, `.claude/` rules + commands + hook.
- [x] `public/index.php` front controller + `Router`; `public/.htaccess`.
- [x] `Config` + `Database` PDO wrapper; `config/.env.example`; `src/bootstrap.php`.
- [x] Security primitives: `Csrf`, `Password` (Argon2id), `SsrfGuard`, `Html`, `SecurityHeaders`.
- [x] `migrations/001_initial_schema.sql` (SPEC §9) + `migrations/run.php`.
- [ ] Copy `.env`, create DB + least-priv app user, run `php migrations/run.php`.
- [ ] Confirm `/` renders a placeholder via the router with security headers set.

**AC:** migrations apply cleanly; `/` returns 200 with CSP + security headers; a bad route
returns a 404 through the router; no secrets in repo.

---

## Phase 1 — Aggregation engine + public page  *(SPEC §6.1–6.3, §8, §12)*

**Goal:** real headlines on a public page, fed by cron; ransomlook + CVE widgets.

Tasks
- `Adapter/SourceAdapter` interface + `NormalizedItem` + `FetchResult` (scaffolded).
- `Service/FeedFetcher`: SSRF-guarded HTTP, **realistic User-Agent**, conditional GET
  (ETag/If-Modified-Since), timeout + size cap, redirect re-validation.
- Adapters: `RssAtomAdapter` (RSS 2.0 + Atom), `RansomlookAdapter` (`/api/recent`),
  `NvdAdapter` (CVE JSON → widget), `JsonApiAdapter` (generic, field-mapped).
- `Service/AggregationService`: resolve adapter → fetch → normalise → **upsert** on
  `(source_id, guid)` → update source health → write `fetch_log`. Backoff + auto-disable.
- `bin/fetch.php`: pick due sources (`next_fetch_at <= now`), global lock, `--source`, `--force`.
- Seed migration `002_seed_sources.sql` from SPEC Appendix A (23 sources; the 5 ⚠️
  Cloudflare ones included — rely on the UA + graceful failure handling).
- `PublicController` + views: main feed column (newest-first, category filter, time window),
  ransomlook widget, CVE widget. Outbound links new-tab + `nofollow`. Attribution per item.

**AC:** `php bin/fetch.php --force` populates `articles`; `/` shows real items grouped/filterable
by category with the two widgets; ransomlook shows CC BY 4.0 attribution; a deliberately broken
source increments `consecutive_failures` and writes a `fetch_log` error row without aborting the run;
all feed titles/summaries are escaped on output (verify with a feed item containing `<script>`).

> **Watch:** CyberSecurityNews/Cybernews/Exploit-DB/Sophos/Datenschutz-Guru return 202/403/5xx to
> datacenter IPs (SPEC Appendix A note 1). Implement UA + retry/backoff; mark as `degraded`, don't crash.

---

## Phase 2 — Accounts & auth  *(SPEC §6.5, §13)*

Tasks
- `002+`: rely on `users`, `auth_tokens`, `sessions`, `login_attempts` (already in schema).
- DB session handler; `AuthService` (login/logout, throttle, generic responses).
- Register → create `pending` user → email single-use verify token → activate on confirm.
  **Verification is mandatory; pending users cannot log in.**
- Change password (current-password check), forgot/reset (hashed single-use token, short TTL).
- Account deletion (DSGVO erasure) + data export (portability).
- `MailService`: SMTP from `.env`, fallback to PHP `mail()` when SMTP unset.

**AC:** full register→verify→login→reset→delete cycle works; unverified login refused; reset/verify
tokens single-use + expiring; no user-enumeration via differing messages/timing; sessions in DB with
`Secure; HttpOnly; SameSite=Lax` cookies; throttling triggers after N failures.

---

## Phase 3 — Customisation  *(SPEC §6.6)*

Tasks
- `/settings/sources` — per-user opt-in/out (`user_sources`).
- `/settings/account` — display name, `default_window_days`, change password.
- `/feed` personalised: **since last visit** (`users.last_seen_at` + unread count, then advance)
  and **last X days** window. Category filter.

**AC:** a logged-in user sees only selected sources; "since last visit" shows the right unread set
and advances `last_seen_at`; window override works.

---

## Phase 4 — Suggestions + admin  *(SPEC §6.7, §6.8)*

Tasks
- `/suggest`: user submits source; server validates + **SsrfGuard-probed** feed detection;
  enters `source_suggestions` as `pending`.
- Admin: source CRUD + approve/enable/disable/remove (category, adapter, interval, attribution);
  suggestion moderation; **feed-health dashboard** (last success/error, failures, items/day,
  status) with "fetch now" + "reset failures"; user admin; audit-log viewer.
- This is where IAPP and BSI get added by hand (deferred from the seed).

**AC:** suggestion → moderation → approval creates a working source; admin can disable a source and it
stops appearing; feed-health reflects real `fetch_log` data; every admin mutation writes `audit_log`.

---

## Phase 5 — Dedup, legal pages, polish, hardening  *(SPEC §6.4, §6.10, §13, §14)*

Tasks
- Dedup: `dedup_key` (normalised title + canonical URL); collapse "also covered by N sources".
- Static pages: Imprint, Terms, Privacy (operator-supplied text; placeholders OK).
- Responsive/accessibility pass (WCAG AA, keyboard, reduced-motion); widget collapse on mobile.
- Maintenance task: article pruning + log rotation.
- Full SSRF/XSS/headers review; `/security-audit`; pen-test the suggestion probe + fetcher.

**AC:** duplicates collapse; legal pages reachable from the footer; AA contrast verified; pruning runs;
security review clean.

---

## Cross-cutting reminders
- Server-rendered, no SPA, no build step. PHP 8.3, `strict_types`, 4-space LF.
- Every outbound fetch through `FeedFetcher` → `SsrfGuard`. Every output through `Html::e()`.
- Keep dependencies minimal; note any addition in the PR description.
