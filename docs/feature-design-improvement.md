Good. I have enough visual context. Here's the critique:

---

## Design Critique: Daybreak

**Overall Impression:** Clean, functional news aggregator with a clear security focus. The layout immediately communicates purpose — but the sidebar competes hard for attention in a way that undermines the main feed, and there are some hierarchy and density issues worth addressing.

---

### Usability

| Finding | Severity | Recommendation |
|---------|----------|----------------|
| The "Last 24h" dropdown is top-right, visually separated from the category filter bar — they're logically related but spatially isolated | 🟡 Moderate | Move the time-window control into or adjacent to the filter pill bar, making it clear they filter the same feed |
| Sidebar (Ransomware Activity + CVEs) has its own independent scroll — users might not notice it scrolls | 🟡 Moderate | Add a subtle "scroll to see more" indicator, or make the panels expandable/collapsible |
| No visual distinction between visited/unvisited article links — users who check the site multiple times daily have no quick way to see what's new | 🟡 Moderate | Rely on browser default link colors or add a subtle "read" indicator |
| "Sign in" and "Register" are both shown in the nav to logged-out users, but "Register" gets a filled button while "Sign in" gets a ghost text link — asymmetry is fine, but "Sign in" is the more common action for returning users | 🟢 Minor | Consider making "Sign in" slightly more prominent; the CTA hierarchy currently prioritizes acquisition over retention |

---

### Visual Hierarchy

**What draws the eye first:** The article titles (bold, good size) compete immediately with the dark sidebar headers ("RANSOMWARE ACTIVITY", "RECENT CVES") in dark navy — the sidebar header treatment has more visual weight than the main feed headline styling.

**Reading flow:** Left column → right sidebar → back to feed. The sidebar distracts mid-scroll.

**Emphasis:** Source badge + category tag above each article headline works well — it clearly establishes provenance before the headline. The timestamp is appropriately de-emphasized. The article excerpt text is a touch light in contrast against the card background.

---

### Consistency

| Element | Issue | Recommendation |
|---------|-------|----------------|
| Source badges vary in visual size — "THE REGISTER (SECURITY)" wraps or gets compressed compared to "THE RECORD" | 🟢 Minor | Consider abbreviated source names or max-width truncation with tooltip |
| Category tags ("Strategic", "Threat Intel") are plain text next to bold badge pills — inconsistent styling | 🟢 Minor | Give the category tag consistent pill styling (possibly a lighter variant) or unify them with the source badge approach |
| The filter pills (top nav) use rounded-pill style; the source badges on cards are rectangular with rounded corners — slightly different radius | 🟢 Minor | Standardize corner radius across interactive chips and badges |

---

### Accessibility

**Color contrast:** The article excerpt text (medium gray on white card) likely falls close to the 4.5:1 WCAG AA boundary — worth verifying. The sidebar body text on the near-white background also warrants a check.

**Touch targets:** The category filter pills look appropriately sized for desktop, but on mobile they may be too tight given 8 pills in one row.

**CVE severity labels** ("MEDIUM (6.3)", "CRITICAL (9.8)") rely on text alone to convey severity — a color-coded indicator (e.g., a small dot or background tint) would give security practitioners faster scanning ability, which is core to the product's value proposition.

---

### What Works Well

- **Information density is well-tuned** — each card gives enough to decide whether to click (source, category, headline, 2-line excerpt) without overwhelming.
- **The three-panel layout** (main feed + ransomware sidebar + CVE sidebar) puts the right data types where security practitioners expect them.
- **Category filter bar** is prominent and feels like a natural first action — good discoverability.
- **The dark navbar** grounds the page and gives the logo space to breathe without eating layout real estate.
- **Relative timestamps** ("just now", "4m ago") are exactly right for this use case.

---

### Priority Recommendations

1. **Add CVE severity color coding** — a red/orange/yellow dot or background stripe on the CRITICAL/HIGH/MEDIUM CVE entries would let users scan the sidebar without reading. This directly serves the core user job-to-be-done: spotting what needs attention fast.

2. **Unify the time filter with the category bar** — currently it looks like an afterthought parked in the top-right corner. Moving it inline with the filter pills (e.g., a separator then the dropdown) makes the filter system feel cohesive.

3. **Reduce sidebar visual weight** — the dark navy "RANSOMWARE ACTIVITY" / "RECENT CVES" headers pull attention aggressively. Lightening them to match the overall light-gray UI tone would let the main feed dominate, with the sidebar as a supporting panel rather than a competing element.

---

## Design Improvement Plan: Whole Product

This plan is intentionally broader than the `/sources` page. The goal is to improve the overall Daybreak experience by tightening the shared design system, clarifying hierarchy in the main feed, and making secondary panels feel supportive instead of dominant.

## Design Goals

1. Make the main news feed the clear primary reading surface.
2. Reduce visual competition from secondary widgets and utility controls.
3. Unify interaction patterns across feed, search, sources, auth, and supporting pages.
4. Improve scan speed for security-oriented content.
5. Raise accessibility and consistency without losing the current compact, practical feel.

## Scope

This plan applies to:

- Shared header and navigation
- Category and time filter system
- Public feed cards
- Widget rail and sidebar panels
- Search page
- Sources page
- Auth and legal pages where shared design tokens apply

This plan does not require a total rebrand. It is an interface refinement plan built on the current visual direction.

## Priority Workstreams

### 1. Shared Shell And Navigation

Goal:

- Make the header and top controls feel intentional, cohesive, and easier to scan.

Problems to solve:

- Logged-out nav hierarchy currently favors `Register` too aggressively over `Sign in`.
- The time-window control feels detached from the category filter system.
- Different page types inherit a shared shell that is functional but not consistently expressive.

Plan:

- Rebalance logged-out CTA hierarchy so `Sign in` gains slightly more visual weight.
- Fold the time-window selector into the category bar instead of leaving it visually parked at the edge.
- Establish one consistent spacing and alignment system for header, filter bar, flash area, and main container.
- Ensure page-level navigation states are visually consistent across feed, search, and sources.

Likely files:

- `src/View/layout.php`
- `src/View/auth_layout.php`
- `src/View/admin_layout.php`
- `public/assets/css/app.css`

### 2. Main Feed Hierarchy

Goal:

- Make the feed feel calmer and easier to scan repeatedly throughout the day.

Problems to solve:

- Sidebar headers can visually overpower article cards.
- Article summary contrast is a little light.
- Source and category metadata are good structurally but inconsistent stylistically.
- Repeat visitors have no quick read/unread cue.

Plan:

- Increase separation between primary headline content and metadata using clearer type scale and contrast.
- Slightly darken summary text for better readability.
- Harmonize `source-badge`, `article-cat`, and top filter chips into one consistent family of tokens.
- Introduce a subtle visited/read-state treatment for article links.
- Add source-name truncation rules where long publisher names create uneven badge widths.

Likely files:

- `src/View/feed/index.php`
- `public/assets/css/app.css`

### 3. Sidebar / Widget Rail Rebalancing

Goal:

- Keep ransomware and CVE widgets useful without letting them dominate the page.

Problems to solve:

- Dark widget headers currently compete too strongly with the feed.
- Independent widget scrolling is easy to miss.
- CVEs are not visually severity-coded, which slows scan speed.

Plan:

- Lighten widget header treatment to reduce contrast dominance.
- Add a small affordance that the widget body scrolls, or move toward collapsible/expandable panels.
- Add severity color cues to CVE entries via dot, stripe, or badge tint.
- Improve visual rhythm inside widgets so links, summary, and timestamp are easier to parse quickly.

Likely files:

- `src/View/layout_end.php`
- `public/assets/css/app.css`

### 4. Cross-Page Consistency

Goal:

- Make search, sources, auth, and legal pages feel like first-class citizens in the same system.

Problems to solve:

- `/sources` currently borrows search and admin table patterns rather than owning a polished public identity.
- Supporting pages use different structural conventions that are functional but not yet fully unified.

Plan:

- Define a reusable public data-page pattern for summary cards + filters + analytics blocks.
- Normalize card padding, heading spacing, and table wrappers across search and sources.
- Review auth and legal pages for spacing, contrast, and title treatment consistency.
- Standardize table density rules for desktop and mobile.

Likely files:

- `src/View/page/sources.php`
- `src/View/search/index.php`
- `src/View/auth/*.php`
- `src/View/page/*.php`
- `public/assets/css/app.css`

### 5. Accessibility And Scan-Speed Enhancements

Goal:

- Improve the interface for both quick monitoring and sustained reading.

Problems to solve:

- Some text colors are near the lower useful contrast threshold.
- Dense chip layouts may become cramped on smaller screens.
- Security-critical severity cues rely too much on text alone.

Plan:

- Review and tighten contrast for summaries, widget text, and helper labels.
- Improve mobile touch target spacing for filter pills and controls.
- Add non-textual severity indicators where risk scanning matters.
- Ensure focus styles remain visible and consistent after visual refreshes.

Likely files:

- `public/assets/css/app.css`
- relevant templates where labels or structure need minor markup support

## Implementation Roadmap

### Phase A: Shared System Cleanup

Focus:

- header hierarchy
- filter-bar cohesion
- spacing and radius consistency
- design-token cleanup

Deliverables:

- improved nav hierarchy
- integrated time filter treatment
- standardized chip/badge radius and spacing

Success criteria:

- header and filter controls feel like one system
- visual weight is more evenly distributed before content begins

### Phase B: Feed And Widget Rebalance

Focus:

- main feed readability
- sidebar weight reduction
- CVE severity scan improvements

Deliverables:

- improved article card hierarchy
- lighter widget headers
- CVE severity visual markers
- clearer widget scroll behavior

Success criteria:

- main feed clearly dominates the reading flow
- users can scan CVEs faster without reading every label

### Phase C: Public Data Pages Polish

Focus:

- search and sources page consistency
- analytics/table readability
- mobile handling for dense data layouts

Deliverables:

- dedicated public analytics/data-page styling
- improved table and card layouts on `/sources`
- alignment of search and sources page structure

Success criteria:

- `/sources` feels designed, not assembled from borrowed patterns
- data tables remain readable without fighting the layout

### Phase D: Supporting Pages Consistency

Focus:

- auth pages
- legal pages
- long-tail consistency issues

Deliverables:

- unified title, card, spacing, and helper text behavior
- cleaned-up secondary page rhythm

Success criteria:

- every public page feels part of the same product language

## Concrete Task List

### Shared Shell

- Rework logged-out nav hierarchy for `Sign in` vs `Register`
- Merge the time-window control visually into the filter bar
- Standardize spacing between header, filter bar, flash, and content start
- Review logo/header balance on mobile and desktop

### Feed Cards

- Darken summary text slightly
- Refine title/meta spacing for better hierarchy
- Normalize source badge width behavior
- Restyle category tags as a consistent companion pill
- Add visited/read-state styling for article links

### Widget Rail

- Lighten widget headers
- Add scroll affordance or collapsible behavior
- Add CVE severity color coding
- Improve widget item spacing and timestamp clarity

### Sources And Search

- Give `/sources` its own public data-page treatment instead of borrowed search/admin visuals
- Rebalance analytics cards and table density
- Unify search and sources filter control styling
- Make analytics sections feel more deliberate, less stacked utility blocks

### Accessibility

- Verify text contrast in summaries and widgets
- Increase mobile pill spacing where needed
- Keep focus indicators visible and consistent
- Avoid meaning conveyed only by color by pairing color cues with text labels where appropriate

## File-Level Plan

### Highest-impact files

- `public/assets/css/app.css`
- `src/View/layout.php`
- `src/View/feed/index.php`
- `src/View/layout_end.php`

### Secondary files

- `src/View/page/sources.php`
- `src/View/search/index.php`
- `src/View/auth_layout.php`
- `src/View/page/*.php`

## Recommended Execution Order

1. Shared shell and filter-bar cleanup
2. Feed card hierarchy and visited-state improvements
3. Widget rail visual rebalance and CVE severity treatment
4. `/sources` and `/search` public data-page polish
5. Auth/legal consistency pass

## Acceptance Criteria For The Design Refresh

- The feed is visually dominant over the sidebar.
- Filtering controls feel like one coherent system.
- Widget panels scan faster and compete less with the feed.
- Search and sources pages share a polished public data-page language.
- Typography, chips, tables, and cards feel like one system across the site.
- Accessibility is improved in contrast, target sizing, and visual cueing.

## Recommended Next Start

Start with **Phase A: Shared System Cleanup**.

Why:

- It improves every page at once.
- It resolves one of the critique's strongest findings: fragmented filtering controls.
- It establishes the design tokens needed before polishing feed cards and widgets.

---

## Phase A Implementation Blueprint (Ready To Build)

This section translates Phase A into concrete implementation steps with minimal risk.

## Phase A Objectives

1. Unify header + filters into one coherent control zone.
2. Rebalance logged-out navigation hierarchy toward return-user flow.
3. Standardize spacing/radius tokens used by chips, badges, and control surfaces.
4. Do this without changing routing, query semantics, or backend behavior.

## File-By-File Change Plan

### 1) `src/View/layout.php`

Changes:

- Move the time-window selector markup into the same structural container as the category pills.
- Introduce a dedicated wrapper class for the "feed controls" area (category + time window).
- Keep all existing filter semantics and query params unchanged.
- Adjust logged-out nav action order and visual emphasis classes so `Sign in` is at least equal prominence.
- Preserve all SEO/meta behavior already implemented.

Non-goals:

- No controller or route changes.
- No new request parameters.

Acceptance checks:

- Category and time filters read as one control group at desktop width.
- On mobile, controls wrap predictably without overlap.
- Existing filter links and selected state continue to work.

### 2) `public/assets/css/app.css`

Changes:

- Add/normalize design tokens for:
	- control radius
	- chip radius
	- shared control spacing
	- shell vertical rhythm (header/filter/content start)
- Create a unified `.feed-controls` layout that supports:
	- inline desktop alignment
	- wrapped mobile alignment
	- consistent spacing between pills and dropdown
- Rebalance nav button styling for logged-out state:
	- raise `Sign in` prominence subtly
	- keep `Register` visible but less visually dominant than today
- Ensure chip/badge radius values match design intent instead of mixed styles.

Non-goals:

- No major color-system rewrite in Phase A.
- No widget rail redesign yet (Phase B).

Acceptance checks:

- No visual collisions at key breakpoints (>=1200px, 992px, 768px, <=480px).
- Header, controls, and content start with consistent vertical spacing.
- Radius and spacing are visually consistent across pills/chips/badges used in shell controls.

### 3) `src/View/auth_layout.php` and `src/View/admin_layout.php`

Changes:

- Apply token-level shell spacing consistency where those layouts share global utility classes.
- Keep auth/admin information hierarchy intact while aligning container rhythm with the refreshed public shell.

Non-goals:

- No admin IA or auth flow changes.

Acceptance checks:

- Header and first content block spacing feels consistent with public pages.
- No regressions to current auth/admin navigation behavior.

### 4) Optional supporting templates

Files:

- `src/View/search/index.php`
- `src/View/page/sources.php`

Changes (only if needed for token adoption):

- Minimal class updates to consume shared control/chip styles.

Acceptance checks:

- Existing sections render identically in structure; only style consistency improves.

## Implementation Sequence (Micro-steps)

1. Add new CSS tokens and utility classes first in `app.css`.
2. Refactor filter/control markup in `layout.php` to use new classes.
3. Rebalance logged-out nav classes in `layout.php`.
4. Apply spacing-token harmonization in auth/admin layouts.
5. Perform responsive pass and fix edge-case wraps.
6. Run tests and manual UI verification pages.

## Regression-Safe Constraints

- Do not rename existing GET params (`category`, `range`, etc.).
- Do not alter controller logic for filtering.
- Do not change route map.
- Keep semantic HTML and accessibility labels on controls.
- Keep CSRF/meta/SEO tags unchanged.

## Verification Checklist

### Functional

- Feed filters still apply correctly for category and time window combinations.
- Active-state styles still match selected category.
- Logged-out nav links remain correct and reachable.

### Responsive

- Desktop: controls on one row where space allows.
- Tablet: controls wrap without clipping.
- Mobile: chips remain tappable and not cramped.

### Accessibility

- Keyboard focus remains visible for nav items and filter controls.
- Form controls retain accessible labeling.
- No contrast regression in header/control areas.

### Stability

- `php tests/run.php` remains green.
- No PHP syntax errors in edited templates.

## Definition Of Done For Phase A

- The top-of-page experience feels cohesive: nav, category pills, and time filter read as one system.
- Logged-out action hierarchy better reflects returning-user behavior.
- Shared spacing/radius tokens are in place and adopted by core shell templates.
- No regressions in filter behavior, routing, or tests.

## Phase A Exit Deliverables

- Updated shell templates with unified controls structure.
- Updated CSS tokens and shared control styles.
- Short implementation note in this file recording any scope decisions made during coding.

## Implementation Progress Notes

### 2026-06-12 — Phase A implemented

- Shared filter controls were unified in the public shell (`category` chips + time window in one control zone).
- Logged-out nav hierarchy was rebalanced: `Sign in` now uses the primary CTA treatment; `Register` uses a subtler secondary style.
- Shared shell rhythm tokens were introduced for consistent horizontal/vertical spacing across public/auth/admin shells.
- Existing filter semantics and query parameters were preserved.

### 2026-06-12 — Phase B implemented

- Feed readability improvements were shipped (visited-link differentiation, summary contrast, clearer article meta hierarchy).
- Sidebar visual weight was reduced by replacing dark widget headers with lighter support-panel headers.
- Widget scroll affordance was added with a persistent "Scroll for more" hint.
- CVE severity now renders with explicit visual coding (severity chip + left accent stripe) derived from summary prefixes.
- Search-page article styles were scoped to `.search-page` to prevent global CSS collisions with feed card styles.

### 2026-06-12 — Phase C implemented

- Search and sources pages now share a common `public-data-page` style layer for panel spacing, table wrappers, and grid rhythm.
- Sources analytics sections and cards were aligned to a unified panel system, including stat-card treatment and shared table framing.
- Search results cards were integrated into the same public data-page pattern without changing search/filter behavior.

### 2026-06-12 — Phase D implemented

- Auth and suggest cards now use the same lighter top-accent panel treatment as the public data pages.
- Legal page container rhythm and heading spacing were tuned to better match the updated public card language.

### 2026-06-12 — Post-phase cleanup

- Repeated top-accent color values were centralized into a shared CSS token (`--color-panel-accent`) to keep panel styling consistent across auth, suggest, legal, and public data pages.
