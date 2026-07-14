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
