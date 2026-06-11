# /security-audit

Run a focused security review before closing any phase touching auth, fetching, or output.

## Checklist
1. **SSRF:** every outbound request goes through `FeedFetcher`; `SsrfGuard::assertSafe()` is
   called pre-connect and post-redirect. No raw fetch of a variable URL anywhere.
2. **SQL:** all queries parameterised via `Database::query()`. Grep for string-built SQL.
3. **XSS:** all output through `Html::e()`; feed summaries sanitised on store. Plant a feed
   item with `<script>` and confirm it renders inert.
4. **Auth:** Argon2id; generic responses (no enumeration); single-use hashed expiring tokens;
   throttling; DB sessions with Secure/HttpOnly/SameSite; unverified users can't log in.
5. **CSRF:** `Csrf::check()` on every state-changing POST.
6. **Headers:** CSP + nosniff + frame-ancestors none + referrer-policy present.
7. **Secrets:** none in repo/logs; `.env` above docroot and gitignored.
8. Run `.claude/hooks/post-write-check.sh` and resolve warnings.

Report findings grouped by severity; propose fixes; don't auto-fix auth logic without showing the diff.
