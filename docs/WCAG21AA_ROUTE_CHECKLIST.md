# WCAG 2.1 AA Route Checklist (Daybreak)

This checklist maps major routes/templates to WCAG 2.1 AA controls used for EN 301 549 web conformance work.
Status values:
- PASS: currently implemented and verified in recent checks
- PARTIAL: implemented in part, known gaps remain
- TODO: not yet verified or not yet implemented

## Route Map

Public:
- /, /public, /category/{slug}, /public/category/{slug} -> PublicController::home -> src/View/layout.php + feed/public views + src/View/layout_end.php
- /sources -> PublicController::sources -> src/View/layout.php + src/View/page/sources.php + src/View/layout_end.php
- /search -> SearchController::search -> src/View/layout.php + src/View/search/index.php + src/View/layout_end.php
- /about -> PageController::about -> src/View/layout.php + src/View/page/about.php + src/View/layout_end.php
- /accessibility -> PageController::accessibility -> src/View/layout.php + src/View/page/accessibility.php + src/View/layout_end.php
- /imprint -> PageController::imprint -> src/View/layout.php + src/View/page/imprint.php + src/View/layout_end.php
- /terms -> PageController::terms -> src/View/layout.php + src/View/page/terms.php + src/View/layout_end.php
- /privacy -> PageController::privacy -> src/View/layout.php + src/View/page/privacy.php + src/View/layout_end.php

Auth:
- /login, /register, /password/*, /verify/* -> AuthController -> src/View/auth_layout.php + auth views + src/View/auth_layout_end.php

User settings:
- /settings/* -> UserController/WebhookController -> src/View/settings_layout.php + user views + src/View/settings_layout_end.php

Admin:
- /admin* -> AdminController -> src/View/admin_layout.php + admin views + src/View/admin_layout_end.php

## Baseline Criteria Checklist

1. 1.1.1 Non-text Content
- PASS: Decorative icons use aria-hidden; logo has alt text.
- TODO: Re-check all admin/action icons for equivalent text labels.

2. 1.3.1 Info and Relationships
- PASS: Major pages use semantic headers/nav/main/sections and label/form bindings.
- PARTIAL: Sortable table headers rely on JS; keep semantic table header scope verified in templates.

3. 1.3.2 Meaningful Sequence
- PASS: Source order in DOM follows visual order for core templates.

4. 1.4.1 Use of Color
- PASS: Inline links in long-form text are underlined and no longer flagged in local verification.

5. 1.4.3 Contrast (Minimum)
- PARTIAL: Remaining automated findings are limited to three native select controls (#sources-category, #search-days, #search-category).

6. 1.4.4 Resize Text
- TODO: Manual test at 200% zoom on desktop and mobile breakpoints.

7. 1.4.10 Reflow
- TODO: Manual test at 320 CSS px width without 2D scrolling for primary tasks.

8. 1.4.11 Non-text Contrast
- PARTIAL: Focus ring and controls improved; verify all component states in dark/light themes.

9. 2.1.1 Keyboard
- PASS: Skip links and focusable main added; scrollable table/widget containers are keyboard-focusable.

10. 2.1.2 No Keyboard Trap
- PASS: No known keyboard traps in current flows.

11. 2.4.1 Bypass Blocks
- PASS: Skip link implemented across shared layouts.

12. 2.4.3 Focus Order
- PASS: Header -> content flow is sequential in shared layouts.

13. 2.4.4 Link Purpose (In Context)
- PASS: Link labels are generally descriptive in navigation and article contexts.

14. 2.4.7 Focus Visible
- PASS: Global focus-visible ring implemented in CSS.

15. 3.1.1 Language of Page
- PASS: html lang="en" is present in shared layouts.

16. 3.2.2 On Input
- PASS: No unexpected context changes beyond expected form submit/search behavior.

17. 3.3.1 Error Identification
- PASS: Flash error blocks exist and now announce via role="alert".

18. 3.3.2 Labels or Instructions
- PASS: Forms include label associations and hints in key views.

19. 4.1.2 Name, Role, Value
- PASS: Theme toggle now exposes aria-pressed and dynamic label.
- PASS: Sortable headers now expose aria-sort.

20. 4.1.3 Status Messages
- PASS: Flash containers/messages use polite live regions/status+alert roles.

## Next Verification Cycle

1. Decide whether to replace native select controls with custom accessible components.
2. Add manual contrast evidence for remaining select controls across browsers/themes.
3. Re-run automated scan on the same URLs and record a dated delta snapshot.
4. Add manual screen-reader checks (NVDA/JAWS/VoiceOver) for login, feed browsing, search, and settings save flows.
