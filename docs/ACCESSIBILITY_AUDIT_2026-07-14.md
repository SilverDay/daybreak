# Accessibility Audit Report (EN 301 549 / WCAG 2.1 AA)

Date: 2026-07-14  
Scope: Public routes of daybreak.silverday.de  
Method: Automated scan with Pa11y + axe runner

## Command Used

```bash
npx --yes pa11y <url> --standard WCAG2AA --runner axe --reporter cli --timeout 45000 --wait 1000
```

Scanned URLs:
- /
- /sources
- /search
- /about
- /imprint
- /terms
- /privacy

## Initial Baseline (production scan)

- /: 51 errors
- /sources: 2 errors
- /search: 3 errors
- /about: 13 errors
- /imprint: 1 error
- /terms: 0 errors
- /privacy: 2 errors

## Post-remediation Verification (local workspace build)

Scanned locally via built-in PHP server on 127.0.0.1 with the updated code.

- /: 0 errors
- /sources: 1 error
- /search: 2 errors
- /about: 0 errors
- /imprint: 0 errors
- /terms: 0 errors
- /privacy: 0 errors

Remaining findings are all the same rule type and all target native select controls:

- #sources-category
- #search-days
- #search-category

Rule:

- color-contrast (WCAG 1.4.3)

Interpretation:

- These are likely browser-native control rendering/measurement constraints under axe for select widgets. High-contrast styles were forced, but the rule still reports on these controls in this environment.

## Error Categories Observed (initial baseline)

1. color-contrast (WCAG 1.4.3)
- Multiple failures in badges and small metadata text (notably source badges and the window label).

2. link-in-text-block (WCAG 1.4.1 + 1.4.3 context)
- Inline links in longer text blocks are not always visually distinguishable without color.

3. scrollable-region-focusable (WCAG 2.1.1)
- At least one scrollable container is not keyboard-focusable in scanned state.

Note: categories 2 and 3 are resolved in the local post-remediation verification.

## Highest-Priority Remaining Items

1. Replace native select widgets with fully custom, WCAG-tested combobox/select components, or add cross-browser manual verification evidence for current controls.
2. Run manual contrast verification for the three reported select controls across Chromium/Firefox/Safari.
3. Re-run automated scans after any select component refactor and compare route-by-route deltas.

## Evidence

Raw scanner output was produced during this audit run at:

- /tmp/daybreak-pa11y-report.txt
- /tmp/daybreak-pa11y-local-final.txt

Note: The temporary path may not persist across environments; keep this report under docs for durable tracking.

## Follow-up: 2026-07-28

Re-ran the same Pa11y/axe scan against production to check the status of the
"Highest-Priority Remaining Items" above.

### Regression found and fixed

`/sources` was reporting new `color-contrast` errors (axe `needsFurtherReview: false`,
i.e. confirmed, not ambiguous) on `.freshness-badge--stale` and `.freshness-badge--degraded`
(`#b45309` text on `#ffedd5` background, ~4.38:1 — below the 4.5:1 AA threshold for this
text size). This widget/feature was introduced after the 2026-07-14 baseline and had not
been scanned before. The identical color pair was also found in
`.status-pill--degraded` (admin sources table), evidently missed when the sibling
`.status-pill--pending` was already correctly set to `#92400e`.

Fix: changed all three rules to `color: #92400e` (Tailwind amber-800), giving ~6.19:1
against `#ffedd5`. Confirmed via re-scan: `/sources` error count dropped from
2 (stale x2 at time of this run) + 1 select to just the 1 select error.

Files: `public/assets/css/app.css` (`.freshness-badge--stale`, `.freshness-badge--degraded`,
`.status-pill--degraded`).

### Native select controls — resolved via manual verification

Per the recommendation above, computed actual rendered contrast for `.filter-select` /
`#sources-category` / `#search-days` / `#search-category`:

- Text `#0f172a` on background `#ffffff`: **17.85:1**
- Border `#334155` on background `#ffffff`: **10.36:1**

Both far exceed WCAG 1.4.3 (4.5:1 text) and 1.4.11 (3:1 non-text UI components). There is
no contrast headroom left to add via CSS. The persistent axe/Pa11y flag
(`needsFurtherReview: true` on every run, including this one) is a known limitation with
color-contrast measurement on native `<select>` elements, not a real defect. Treating this
as resolved; no further select-replacement work is warranted based on contrast alone.

### Other pages

`/`, `/about`, `/imprint`, `/terms`, `/privacy` — no regressions (0 errors except a
`needsFurtherReview: true` flag on the homepage `.widget-attribution` link, same
false-positive category as the selects: computed contrast `#0f172a` on `#ffffff` is
~17.85:1).

### Account-gated pages — scanned for the first time, one regression found and fixed

The 2026-07-14 baseline only covered public routes. To close that gap, a temporary,
clearly-labeled test account (`accessibility-audit-test@daybreak.silverday.de`) was
created directly via `AuthService`, activated by setting `status='active'` directly
(bypassing the real email-verification round-trip), and seeded with one starred article
and one watch term so populated (not just empty-state) markup would be scanned. Pa11y/axe
was driven through the real `/login` form (via Puppeteer actions: fill `#email`/`#password`,
submit, wait for redirect to `/feed`) so the same session cookie carried into each
subsequent scan.

Scanned: `/feed`, `/starred`, `/settings/account` (general settings — the route the
`showAccount()` controller method actually renders), `/settings/security`,
`/settings/sources`, `/settings/widgets`, `/settings/watch`, `/settings/webhooks`.
(`/settings/export` was not scanned — it returns a JSON file download, not HTML.)

Found: `link-in-text-block` (WCAG 1.4.1, `needsFurtherReview: false` — confirmed, not
ambiguous) on `/settings/account` — the inline "Kioju" link in
`src/View/user/general.php:57` relied on color alone to be distinguished from the
surrounding paragraph text.

Fix: added `.form-hint a { text-decoration: underline; }` to `public/assets/css/app.css`
(scoped — `.form-hint` has no other inline-text anchors anywhere in the codebase, so this
doesn't affect the standalone-URL `.text-link` anchors used elsewhere). Re-scan of
`/settings/account` after the fix: **No issues found.**

### Legal scope correction: enforcement procedure

The statement's "Enforcement procedure" section previously named a generic "competent
accessibility enforcement body in Germany" — boilerplate carried over from the
public-sector BITV 2.0 template. Daybreak is a free, non-commercial personal project:
it is not a public-sector body (so BITV 2.0 / the EU Web Accessibility Directive don't
bind it), and it is not an obviously-covered consumer service under the BFSG (Germany's
EAA transposition), which in any case exempts microenterprises. There is therefore no
statutory Durchsetzungsstelle with jurisdiction over this site. Reworded "Conformance
target" and "Enforcement procedure" in `src/View/page/accessibility.php` to state the
WCAG/EN 301 549 target is adopted voluntarily and that no formal escalation body exists
beyond direct contact. (Not a certified legal opinion — flagged for the site owner's own
judgment given the specific facts.)

All other gated pages scanned clean on first pass. The test account and its seeded rows
(`user_watch_terms`, `user_starred_articles`) were deleted after the scan.
