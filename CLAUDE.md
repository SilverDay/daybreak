# Daybreak — Claude Code Project Memory

## Project overview
Daybreak is a self-hosted **security news aggregator**. It pulls headlines (+ feed
summaries where available) from an admin-managed list of security/privacy sources,
plus a live ransomware view from ransomlook.io and a CVE view from NIST NVD, and
shows them in one responsive, server-rendered interface.

- **Owner:** Klaus-E. Klingner / SilverDay Media
- **Deploy root:** `/srv/vhosts/daybreak.silverday.de/` → `https://daybreak.silverday.de`
- **Apache DocumentRoot:** `public/` (everything else lives above the docroot)
- **Full spec:** `docs/SPEC.md` · **Build plan:** `docs/IMPLEMENTATION.md`

The single most important architectural fact: **the web layer never fetches feeds.**
A cron CLI (`bin/fetch.php`) fetches and caches into the `articles` table; the web
layer only reads that cache.

## Stack & conventions

- **PHP 8.3** — `declare(strict_types=1);` at the top of every file. No exceptions.
- **Namespace root:** `Daybreak\` → maps to `src/` (PSR-4).
- **MariaDB 10.11+**, InnoDB, `utf8mb4_unicode_ci`. Access only via the `Database`
  wrapper using **prepared statements**. Never string-interpolate input into SQL.
- **No framework.** Custom front controller + router, `Database` PDO wrapper, no
  Composer runtime dependencies unless explicitly justified in a PR note.
- **Server-rendered.** Plain PHP template includes (no Twig/Blade, no SPA, no build
  step). Controllers set variables, then `include` the template.
- **Output escaping is mandatory:** every dynamic value rendered to HTML goes through
  `Html::e($val)` (= `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`). Feed content is
  untrusted — see `.claude/rules/templates.md`.
- **Indentation** 4 spaces, no tabs. **Line endings** LF (Unix).
- **Config:** `config/.env` (copy from `config/.env.example`), loaded by `Config`.
  Lives **above** the docroot; never committed.

## Security non-negotiables (this is a security tool — act like it)

1. **SSRF guard on every outbound fetch.** The fetcher and the source-suggestion probe
   accept supplied URLs. All outbound HTTP goes through `FeedFetcher`, which calls
   `SsrfGuard::assertSafe($url)` — http/https only, DNS-resolved, private/reserved/
   link-local/metadata ranges blocked, re-checked after redirects, with timeouts and a
   response-size cap. Never `curl`/`file_get_contents` a supplied URL directly.
2. **Untrusted feed content.** Titles/summaries from feeds may contain HTML/JS. Sanitise
   on store (`Html::sanitizeSummary()`), escape on output (`Html::e()`). Never echo feed
   data raw.
3. **Auth baseline (NIST SP 800-63B):** Argon2id (`Password` class), ≥12 char minimum,
   no forced rotation/composition. DB-backed sessions. Login throttling. Generic
   auth responses (no user enumeration on login/register/reset). Reset/verify tokens are
   random, stored only as SHA-256 hashes, single-use, short-TTL.
4. **CSRF** token on every state-changing POST: `Csrf::check()` right after the auth guard.
5. **Outbound article links** always `target="_blank" rel="noopener noreferrer nofollow"`.
6. **Secrets** only in `config/.env`. Separate MariaDB users (app / migrator) least-priv.
7. **Attribution:** every item shows its source; ransomlook data shows persistent
   "Data: ransomlook.io (CC BY 4.0)".

## Directory map

```
daybreak/
├── CLAUDE.md                         ← you are here
├── .claude/
│   ├── settings.json                 ← allow/deny perms + PostToolUse hook
│   ├── hooks/post-write-check.sh     ← grep-based security scan after writes
│   ├── rules/                        ← scoped rules (load by glob/context)
│   │   ├── php-security.md           ← all *.php
│   │   ├── sql.md                    ← Database access
│   │   ├── templates.md              ← output escaping (templates/)
│   │   ├── fetching.md               ← SSRF + HTTP fetch rules (Service/, bin/)
│   │   └── adapters.md               ← how to write a SourceAdapter
│   ├── commands/                     ← slash commands
│   │   ├── new-adapter.md
│   │   ├── new-controller.md
│   │   ├── new-migration.md
│   │   └── security-audit.md
│   └── agents/security-reviewer.md
├── config/.env.example               ← copy to config/.env (gitignored)
├── deploy/apache-vhost.conf
├── public/                           ← Apache DocumentRoot
│   ├── .htaccess                     ← front-controller rewrite + hard denies
│   ├── index.php                     ← front controller + route table
│   └── assets/css/app.css
├── src/
│   ├── bootstrap.php                 ← autoload, Config, error handling
│   ├── Config.php                    ← Config + Database PDO wrapper
│   ├── Router.php                    ← method+path → handler
│   ├── Security/
│   │   ├── Csrf.php  Password.php  SsrfGuard.php  Html.php  SecurityHeaders.php
│   ├── Adapter/
│   │   ├── SourceAdapter.php         ← interface
│   │   ├── NormalizedItem.php  FetchResult.php
│   │   ├── RssAtomAdapter.php  RansomlookAdapter.php   (NvdAdapter, JsonApiApter: Phase 1+)
│   ├── Service/
│   │   ├── FeedFetcher.php           ← SSRF-guarded HTTP w/ conditional GET + UA
│   │   └── AggregationService.php    ← orchestrates fetch → normalise → upsert
│   └── Controller/PublicController.php
├── bin/fetch.php                     ← cron entry point
├── migrations/
│   ├── run.php                       ← apply *.sql in order, tracked in schema_migrations
│   └── 001_initial_schema.sql        ← full schema (SPEC §9)
├── storage/                          ← cache, logs (gitignored, above docroot)
└── docs/SPEC.md  docs/IMPLEMENTATION.md
```

## Database access pattern

```php
use Daybreak\Database;

$rows = Database::query(
    'SELECT id, title, url FROM articles WHERE source_id = ? ORDER BY published_at DESC LIMIT ?',
    [$sourceId, 50]
)->fetchAll();
```
- Always parameterised. No exceptions.
- One concern per query; keep controllers thin. Heavier logic goes in `Service/`.

## Template / layout convention

```php
// Controller sets these, then includes:
$title     = 'Latest';
$activeNav = 'feed';
include DB_ROOT . '/src/View/layout.php';        // <head>, header, opens main
include DB_ROOT . '/src/View/feed/index.php';    // page body
include DB_ROOT . '/src/View/layout_end.php';    // widget rail, footer, close
```
- Every page: `<meta name="csrf-token" content="<?= Html::e(Csrf::token()) ?>">`.
- Footer on every page links Imprint / Terms / Privacy.
- Layout = main feed column + widget rail (ransomlook + CVE). See SPEC §11.

## Flash messages
- Read once: `$_SESSION['flash'] ?? null; unset(...)`. Error key: `$_SESSION['flash_error']`.

## Build order
Work **phase by phase** per `docs/IMPLEMENTATION.md` (Phase 0 → 5). Don't jump ahead;
each phase has acceptance criteria. Run `/security-audit` before closing any phase that
touches auth, fetching, or template output.

## Commands
- Apply migrations: `php migrations/run.php`
- Fetch feeds once (manual): `php bin/fetch.php --force`
- Fetch a single source: `php bin/fetch.php --source=bleepingcomputer`
- Cron (every 5 min): `*/5 * * * * php /srv/vhosts/daybreak.silverday.de/bin/fetch.php`

## Do / Don't
- ✅ `declare(strict_types=1);`, prepared statements, `Html::e()` on output, SSRF guard on fetch.
- ✅ Keep dependencies minimal; justify any new one.
- ❌ No raw echo of feed/user data. ❌ No direct fetch of supplied URLs. ❌ No SPA/build step.
- ❌ No secrets in code or git. ❌ No fetching feeds from the web request path.
