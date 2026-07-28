# Daybreak — Improvement Backlog

Generated 2026-07-28 from three reviews: external perimeter, source code, and UI/UX.
Baseline commit: `3a05631e48e030f886e8ff519bd28004acd4f201`

Follows the checkbox convention of `tasks/todo.md`. Suggested location: `tasks/todo-hardening.md`.

---

## How to run this with Claude Code

Work one phase per session; phases are ordered by risk, not by effort. For each task:

1. Read the referenced file(s) before editing.
2. Make the change.
3. Run `php tests/run.php` and fix regressions.
4. Commit per task, not per phase — these are independently revertable.

Phase 1 has a test-first item (P1.4). Write those tests before P1.1 so you can watch them go from red to green.

---

## Resolved — all three blockers answered 2026-07-28

- **OpenSSL:** Ubuntu 24.04 LTS ships OpenSSL 3.0.13. The 1.1.1f TLS 1.3 HelloRetryRequest bug the code comment cites does not exist in 3.0.x. **P4.4 unblocked — remove the ceiling.**
- **Registration:** open to anyone, gated only by an email-link verification. That is not an access barrier — disposable addresses are free and instant. **This confirms Phase 1 as HIGH severity: any internet user can reach the SSRF paths.** Do Phase 1 first, before anything else on this list.
- **Subdomains:** all `*.silverday.de` hosts are HTTPS-only. Apex verified 2026-07-28 as serving `max-age=31536000; includeSubDomains` and 301-ing HTTP→HTTPS. **P3.6 unblocked.**

---

## Phase 1 — SSRF guard (highest risk)

`SsrfGuard::isPublicIp()` classifies IPv4-mapped IPv6 as public. Verified on PHP 8.3.6:
`::ffff:127.0.0.1`, `::ffff:169.254.169.254`, and `0:0:0:0:0:ffff:7f00:1` all pass.
Reachable by **any authenticated user** via `WebhookController:98` and `SuggestionService::probe()`.

- [x] **P1.4 (do first) — SSRF regression tests.** New `tests/SsrfGuardTest.php`.
  Must block: `::ffff:127.0.0.1`, `::ffff:169.254.169.254`, `0:0:0:0:0:ffff:7f00:1`, `100.64.0.1`, `100.100.100.200`, `198.18.0.1`, `192.0.0.192`, `127.0.0.1`, `10.0.0.5`, `192.168.1.1`, `169.254.169.254`, `::1`, `fe80::1`, `fd00::1`.
  Must allow: `8.8.8.8`, `1.1.1.1`, `93.184.216.34`, `2001:4860:4860::8888`, `::ffff:8.8.8.8`.
  *Acceptance:* tests fail against current `SsrfGuard`.

- [x] **P1.1 — Rewrite `isPublicIp()`.** `src/Security/SsrfGuard.php`.
  Replace the `FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE` check with:
  (a) a `normalizeIp()` helper collapsing `::ffff:a.b.c.d` to `a.b.c.d` via `inet_pton`/`inet_ntop`;
  (b) an explicit CIDR denylist.
  IPv4 deny: `0.0.0.0/8`, `10.0.0.0/8`, `100.64.0.0/10`, `127.0.0.0/8`, `169.254.0.0/16`, `172.16.0.0/12`, `192.0.0.0/24`, `192.0.2.0/24`, `192.168.0.0/16`, `198.18.0.0/15`, `198.51.100.0/24`, `203.0.113.0/24`, `224.0.0.0/4`, `240.0.0.0/4`.
  IPv6 deny: `::/128`, `::1/128`, `fc00::/7`, `fe80::/10`, `2001:db8::/32`, `ff00::/8`, `64:ff9b::/96`, `2002::/16`.
  Working implementation is in `daybreak-source-review.md` §Remediation — it passes all 19 P1.4 cases.
  *Acceptance:* P1.4 green.

- [x] **P1.2 — Apply `normalizeIp()` on the literal-IP path in `resolve()`.**
  Without this, `http://[::ffff:127.0.0.1]/` submitted directly skips DNS and the new denylist.
  *Acceptance:* a test with that literal URL throws.

- [x] **P1.3 — Pin the validated address with `CURLOPT_RESOLVE`.**
  `assertSafe()` currently resolves via `dns_get_record()`, then `curl_init($url)` re-resolves independently — a DNS-rebinding TOCTOU.
  Change `assertSafe()` to return `array{host, ip, port}`. In `FeedFetcher`, add
  `CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"]` at all four call sites: `:45`, `:121`, `:174`, `:231`.
  Recompute the pin **inside** the redirect loop — each hop needs its own.
  Keep the existing conservative behaviour of rejecting the URL if *any* resolved address is non-public.
  TLS verification still runs against the hostname, so certificate checking is unaffected.
  *Acceptance:* redirect chains still work against a real feed; pinned IP appears in `curl_getinfo(CURLINFO_PRIMARY_IP)`.

---

## Phase 2 — Privacy and data lifecycle

- [x] **P2.1 — `user_watch_terms` has no foreign key.** New migration.
  `user_id` is `INT UNSIGNED NOT NULL` with an index but **no FK constraint**, while `users.id` is `BIGINT UNSIGNED`. `AuthService::deleteAccount()` therefore leaves watch terms orphaned — an Art. 17 gap on the most sensitive data in the system (what a security professional monitors).
  Order matters: (1) `DELETE FROM user_watch_terms WHERE user_id NOT IN (SELECT id FROM users)`; (2) `ALTER ... MODIFY user_id BIGINT UNSIGNED NOT NULL`; (3) add `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE`.
  The `ALTER` fails if orphans remain, so don't reorder.
  *Acceptance:* create user → add watch term → delete account → zero rows remain.

- [x] **P2.2 — State an `audit_log` retention period.** `src/View/page/privacy.php`, `bin/prune.php`.
  `audit_log` is not in the prune set and grows unbounded. Retaining it is defensible; Art. 5(1)(e) wants the period stated. Pick one (12 or 24 months), document it on the privacy page, add a `PRUNE_AUDIT_DAYS` env var and a `pruneTable()` call.
  Note `ON DELETE SET NULL` on `fk_audit_user` is correct and should stay — erasure severs identity while the security trail survives.

- [x] **P2.4 — Isolate mail reputation for registration emails.** *Will not be fixed.*
  Open signup means Postfix on this VPS sends verification mail to arbitrary attacker-supplied addresses. Bounces and spam complaints from that traffic degrade the sending reputation of the whole box, which is the same box handling your real mail.
  Confirm: bounces are actually processed rather than silently dropped; `MAX_REGISTER_IP = 10`/hour (`AuthService:28`) is enforced *before* the send, not after; and unverified `pending` accounts stop generating resends.
  Consider a dedicated envelope-from subdomain for transactional mail so Daybreak's reputation is separable from silverday.de's, with its own SPF/DKIM/DMARC alignment.

- [x] **P2.3 — Verify `webhook_log` cleanup.** Confirm it cascades via `user_webhooks` on account deletion. If it links only by `webhook_id` with no cascade, add one, and add it to `prune.php`.

---

## Phase 3 — Headers and perimeter

- [x] **P3.1 — Apache is overriding the application's security headers.**
  `SecurityHeaders.php` sends `X-Frame-Options: DENY`; the live site returns `sameorigin`. An Apache `Header set` directive is clobbering PHP's header.
  Find it (`grep -ri x-frame-options /etc/apache2/`), remove it, let the application own the header. Then audit the vhost for any other `Header set` that shadows `SecurityHeaders::send()`.
  *Acceptance:* `curl -I https://daybreak.silverday.de/ | grep -i x-frame` returns `DENY`.

- [x] **P3.2 — Extend the CSP.** `src/Security/SecurityHeaders.php`.
  Add `form-action 'self'; frame-src 'none'; upgrade-insecure-requests`.
  `form-action` is the meaningful one: the CSP blocks injected script but not an injected form posting to an external host.
  *Acceptance:* login and all settings forms still submit.

- [x] **P3.3 — Add COOP/CORP.** Same file.
  `Cross-Origin-Opener-Policy: same-origin`, `Cross-Origin-Resource-Policy: same-origin`.
  Use `same-origin-allow-popups` instead **only if** something uses a popup that posts back to its opener. Nothing found in `app.js`, but confirm.

- [x] **P3.4 — Trim `robots.txt`.** Remove `Disallow:` for `/admin`, `/password`, `/settings`, `/suggest`, `/search`.
  `shouldSendNoIndexHeader()` already covers every one of those paths via `X-Robots-Tag`, so nothing is lost — you stop publishing a map of privileged routes. Keep `Allow: /` and the sitemap line.

- [x] **P3.5 — Add `/.well-known/security.txt`** (RFC 9116): `Contact:`, `Preferred-Languages:`, `Expires:`. Set a calendar reminder for the expiry.

- [x] **P3.6 — HSTS `preload`.** *Obsolete / will not be fixed.*
  Apex already meets every submission prerequisite except the token itself: valid cert, HTTP→HTTPS 301, `max-age=31536000`, `includeSubDomains`. Only change needed is appending `preload` **on the apex `silverday.de` vhost** — submission is per registrable domain, not per subdomain. Adding it to `daybreak` too is harmless and consistent.
  Then submit at hstspreload.org.
  Two things to accept before submitting: removal takes months (removal request plus browser release cycles), and **every future `*.silverday.de` subdomain must be HTTPS from day one** — including short-lived test hosts. HSTS is browser/HTTP-only, so SMTP and IMAP on the mail host are unaffected; only web UIs on any subdomain matter.

- [x] **P3.7 — Explicit caching for versioned assets.**
  Assets already carry `?v=<mtime>` and return an `ETag`, but no `Cache-Control`. Add `Cache-Control: max-age=31536000, immutable` for `/assets/` in the vhost.
  Separately: every HTML response carries `no-store, no-cache, must-revalidate` (PHP's session default) — including the anonymous article list. Consider `private, max-age=60` for guest responses only. Leave authenticated responses `no-store`.

---

## Phase 4 — Correctness and hygiene

- [x] **P4.1 — `HEAD /` returns 404 while `GET /` returns 200.** `public/index.php`.
  The router doesn't handle `HEAD`. Uptime monitors and link checkers use it first. Route `HEAD` as `GET` and suppress the body.
  *Acceptance:* `curl -I https://daybreak.silverday.de/` returns 200.

- [x] **P4.2 — Start sessions lazily.** *Will not be fixed.* A fresh `PHPSESSID` is issued on every anonymous request including `OPTIONS` and 404s. Start only on routes that need state. (`session_regenerate_id(true)` on login and remember-me is already correct — leave it.)

- [x] **P4.3 — `SuggestionService:36` parses without `LIBXML_NONET`.**
  Match `RssAtomAdapter:34`: `simplexml_load_string($xmlBody, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET)`. The entity loader is already nulled, so this is defence-in-depth — but the two parsers of untrusted feed XML shouldn't differ.

- [x] **P4.4 — Remove the outbound TLS ceiling.** `FeedFetcher.php:71` and `:245`. *Unblocked — confirmed obsolete.*
  `CURL_SSLVERSION_TLSv1_2 | CURL_SSLVERSION_MAX_TLSv1_2` disables TLS 1.3 on every feed fetch. The comment cites an OpenSSL 1.1.1f bug on Ubuntu 20.04; the host is Ubuntu 24.04 with OpenSSL 3.0.13, where that bug does not exist.
  Change both to `CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2` — a floor, not a ceiling — so TLS 1.3 is negotiated where the remote supports it.
  Delete the stale comment while you're there; it will otherwise mislead the next reader.
  *Acceptance:* a fetch against a TLS 1.3 host reports `CURLINFO_SSL_VERSION` as TLSv1.3.

- [x] **P4.5 — `extract($vars)` → `extract($vars, EXTR_SKIP)`.** `AuthController.php:183`. Not exploitable today; the array is developer-controlled. Cheap insurance.

- [x] **P4.6 — Set `CURLOPT_SSL_VERIFYPEER => true` and `CURLOPT_SSL_VERIFYHOST => 2` explicitly** in all four `FeedFetcher` option arrays. PHP's defaults are already secure; explicit makes it auditable and immune to a config change.

- [x] **P4.7 — IPv6 literal URLs are unusable.** *Will not be fixed.* `parse_url('http://[2001:db8::1]/')` yields host `[2001:db8::1]` with brackets, which fails validation and then DNS. Fails closed, so it's correct-but-accidental. Strip brackets before validating if you want IPv6 literal feeds. Optional.

- [x] **P4.8 — Close the stale finding in `tasks/security_findings.md`.** The CSRF empty-token edge case appears already fixed — `Csrf::check()` guards `$expected === ''` and `$sent === ''` before `hash_equals`. Verify and mark resolved.

---

## Phase 5 — Accessibility

- [x] **P5.1 — No `<h1>` on the homepage.** Heading census: 2 × `h2`, 60 × `h3`, zero `h1`. Add one in `src/View/layout.php` or the page view — "Latest" or the page title. Verify the outline doesn't skip levels afterwards.

- [x] **P5.2 — Emit per-article `lang`.** WCAG 3.1.2 Language of Parts (Level AA).
  `<html lang="en">` while German headlines from CERT-Bund, heise, and Golem render untagged — a screen reader speaks them with an English voice.
  The data already exists: `sources.language` (ISO 639-1, migration 006), admin-editable against an allowlist.
  Select it in `PublicController` and `FeedController`, emit `lang="<?= Html::e($row['language']) ?>"` on the article element where non-null.
  *Acceptance:* German cards carry `lang="de"` on the guest homepage.

- [x] **P5.3 — `--color-text-muted` (`#64748b`) fails WCAG AA.** `public/assets/css/app.css` `:root` (line 3) and `[data-theme="dark"]` (line 57).
  Measured: 4.34:1 on light page bg `#f1f5f9`; **3.07:1** on dark card `#1e293b`; 3.75:1 on dark page bg. AA needs 4.5:1.
  Applied to `.article-time`, `.cve-item`, `.ransom-item`, and `.article-card--read` — so the dark-mode read state is the worst case.
  Approximate targets: `#5a6b80` (light), `#8ba0b8` (dark). Re-measure after changing; don't trust these as final.

- [x] **P5.4 — Faint focus ring on `.search-input`.** `app.css:2064-2067` replaces `outline` with `box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12)`. At 12% opacity that's barely visible. Raise opacity or use the standard `--focus-ring` outline as everywhere else.

- [x] **P5.5 — Verify `--color-success` usage.** `#16a34a` on white is 3.30:1 — fails AA for normal text. Only found in `.flash-success`; fine as a border or background, not as body text. Confirm which.

---

## Phase 6 — Guest experience

Framing: Daybreak is a public good, not a funnel. The guest page **is** the product; accounts add continuity plus the three things that can't live in a browser (watch terms, webhooks, feed tokens). No conversion mechanics, no teasers, no counters.

- [x] **P6.1 — Language filter for guests.** Highest-value item in this phase.
  `sources.language` already drives a filter at `FeedController:78`, but only for logged-in users. Guests see mixed German/English with no way to narrow.
  Add a `lang` URL parameter to `PublicController` alongside the existing `days` select. Pairs naturally with P5.2 — the same query change serves both.

- [x] **P6.2 — Guest read-state in `localStorage`.** *Will not be fixed.* Key by article ID, reuse the existing `.article-card--read` style. Works instantly, no account, GDPR surface of zero. Add one factual line where it's exposed: *"stored in this browser only."*

- [x] **P6.3 — Public archive / pagination.** Guests get 60 cards and a 24h–30d select with no next page; anything older is reachable only via `/search`. Retention is 90 days, so there is real history to expose. `.feed-pagination-info` already exists in the CSS.

- [x] **P6.4 — Explicit "Mark all read" boundary.** *Will not be fixed.* For the all-day grazing pattern.
  Two read-tracking systems run in parallel: the implicit `last_seen_at` watermark (`touchLastSeen()`, 5-minute floor) and explicit rows in `user_article_reads`. Merely *loading* the page consumes unread state — glance at the tab between meetings and those items are seen forever.
  Advance the watermark on an explicit action instead of a timer. The explicit read table is already there to build on.

---

## Deliberately not on this list

Verified correct during review; no action needed:

Argon2id with `needsRehash` on login · `session_regenerate_id(true)` on login and remember-me · per-email and per-IP login throttling with hashed IPs · remember-me tokens rotated per use and purged on password change · CSRF via `hash_equals` on a 256-bit per-session token · fully parameterized SQL with an `ORDER BY` `match()` allowlist · manual redirect handling with per-hop SSRF re-check · `CURLOPT_PROTOCOLS` restricted · response size caps · webhook guard applied at delivery, not just creation · `bin/prune.php` retention · self-service account deletion · `audit_log` `ON DELETE SET NULL` · per-article attribution on the guest page · design tokens, dark mode with system fallback, skip link, `.sr-only`, `:focus-visible`, `prefers-reduced-motion` · full landmark set · `alt` on every image · keyboard-operable sortable tables.
