# Daybreak Feature: User-Assigned Sidebar Widgets

## Summary

The right rail currently renders two fixed widgets (Ransomware Activity and Recent CVEs).
This feature lets authenticated users assign their own favorite sources to two widget slots,
so the rail reflects personal priorities instead of a hardcoded global default.

The feature is intentionally scoped to user pages and cached article data only.
No live fetching is introduced into request handling.

## Goals

- Let each authenticated user choose the source shown in widget slot 1 and slot 2.
- Keep the current two-widget layout and responsive behavior.
- Reuse cached `articles` data only (no request-time outbound fetches).
- Keep defaults for users who never configure widget slots.
- Preserve security, privacy, and output-escaping guarantees.

## Non-Goals

- No drag-and-drop widget builder in v1.
- No unlimited widget count.
- No changes to cron fetch behavior.
- No public/anonymous personalization in v1.
- No per-widget custom query syntax (keywords, regex, etc.) in v1.

## User Story

As a logged-in user, I want to pin two favorite sources to the right rail widgets, so I can
quickly monitor the sources that matter most to me while reading My Feed.

## UX Proposal

### Settings Location

Add a new settings tab:

- `GET /settings/widgets`
- `POST /settings/widgets`

The page contains two selectors:

- Widget slot 1 source
- Widget slot 2 source

Validation rules:

- The same source cannot be selected in both slots.
- Source must be eligible from the global allowlist.
- Invalid selections are rejected with a flash error.

### Widget Rendering

For authenticated feed pages that already show widgets:

- If user has configured slots, render selected sources per slot.
- If a slot is not configured, render its default content.
- If configured source later becomes unavailable (disabled/deleted/ineligible), that slot falls back to its default content.

### Mobile

Keep existing behavior:

- rail drops below feed
- widgets remain collapsible/scrollable as implemented today

## Eligibility Rules For Sources

To keep implementation low-risk and UI-consistent in v1, eligible sources are:

- `sources.status IN ('active','degraded')`
- `sources.adapter_type IN ('rss_atom','json_api','cisa_kev')`

Rationale:

- These already produce article-style entries used in the main feed.
- It avoids adapter-specific rendering paths for `ransomlook` and `nvd` in the first release while still allowing users to pick `cisa_kev` as a familiar default-like source.

Future extension can explicitly allow special adapters with dedicated templates.

## Data Model Changes

Add a new migration creating `user_widget_sources`:

```sql
CREATE TABLE user_widget_sources (
  user_id INT UNSIGNED NOT NULL,
  slot TINYINT UNSIGNED NOT NULL,
  source_id INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, slot),
  KEY idx_widget_source (source_id),
  CONSTRAINT fk_uws_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_uws_source FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE SET NULL,
  CONSTRAINT chk_widget_slot CHECK (slot IN (1,2))
);
```

Notes:

- Append-only migration policy applies.
- `source_id` nullable allows "use slot default" without deleting the row.
- Slots are fixed to 1 and 2 in v1.

## Backend Changes

### UserController

Add endpoints to render and handle widget preference form:

- load eligible sources (same language filter behavior as feed source settings where appropriate)
- load current slot mapping
- validate and upsert both slot rows

Selection is independent from a user's main feed source toggles. Users may pick any globally eligible widget source.

All writes must use bound parameters via `Database::query()`.

### FeedController

Replace hardcoded widget queries for authenticated feed pages with:

1. Load user slot selections from `user_widget_sources`.
2. If absent, use default hardcoded widgets (current behavior).
3. For each configured source slot, query recent items:
   - `SELECT title, url, published_at FROM articles WHERE source_id = ? ... LIMIT ?`
4. Pass a normalized widget array to the view.

If a slot has no configured source, render the slot default widget content.

The existing hardcoded defaults remain the fallback path.

### Shared Query Logic

Add a small service helper (or private controller method) for widget data lookup to avoid duplicated SQL in multiple controllers that may later adopt user widgets.

## View Changes

### Settings UI

Add new template under user settings for widget source assignment.

### Right Rail

Refactor `layout_end.php` from fixed sections to loop over two generic widget slots:

- Widget title uses source name.
- Attribution uses source attribution text if present.
- Items render as linked headlines with relative time.
- Empty state when no items exist in the selected period.

Keep strict escaping:

- All dynamic strings through `Html::e()`.
- Outbound links keep `target="_blank" rel="noopener noreferrer nofollow"`.

## Backward Compatibility

- Existing users without `user_widget_sources` rows continue seeing current hardcoded widgets.
- New users also get defaults until they save widget preferences.
- No change to public page behavior in v1.
- No slot is hidden in v1; each slot always resolves to either custom source content or default content.

## Security And Privacy Considerations

- CSRF check required on `POST /settings/widgets`.
- Authorize user identity server-side; never accept user_id from request body.
- Validate `source_id` against allowlisted eligible source set.
- Use prepared statements only.
- Continue escaping all widget output.
- No additional personal data beyond preference mapping `(user_id, slot, source_id)`.

## Performance Considerations

- Two extra small article queries per request in the configured case.
- Add index `(source_id, published_at)` if query plans show regressions.
- Optional later optimization: fetch both slots in one query and group in PHP.

## Rollout Plan

### Phase 1 (MVP)

- Migration for `user_widget_sources`
- Settings page with two selectors
- FeedController support + fallback defaults
- Generic widget rendering for configured slots
- Tests

### Phase 1 Implementation Plan

This section converts the MVP scope into an execution sequence with explicit file touchpoints.

#### Workstream 1: Schema (migration 019)

Objective:

- Add durable storage for per-user slot assignments.

Deliverables:

- New migration file `migrations/019_user_widget_sources.sql`.
- Table `user_widget_sources` with PK `(user_id, slot)` and nullable `source_id`.

Checks:

- Migration runs cleanly via `php migrations/run.php`.
- FK behavior is correct for user delete and source delete.

#### Workstream 2: Routes and Controller Surface

Objective:

- Expose settings endpoints and save flow.

Deliverables:

- Register routes in `public/index.php`:
  - `GET /settings/widgets`
  - `POST /settings/widgets`
- Add `UserController::showWidgets()` and `UserController::handleWidgets()`.

Checks:

- Auth required on both endpoints.
- `Csrf::check()` enforced on POST.
- Redirect target after save is `/settings/widgets` with flash message.

#### Workstream 3: Settings UI

Objective:

- Provide a clear two-slot assignment screen.

Deliverables:

- Add settings tab link in `src/View/settings_layout.php`.
- New template `src/View/user/widgets.php` with two source selectors.

Validation behavior:

- Duplicate source IDs across slot 1 and 2 rejected.
- Submitted source IDs must be in the global eligible set.
- Empty selection for a slot stores `NULL` (slot default behavior).

Checks:

- Form includes CSRF token.
- Current selections are pre-populated.
- Flash success/error messages render through existing settings layout.

#### Workstream 4: Feed Rendering Integration

Objective:

- Replace hardcoded authenticated widget rail behavior with slot-aware logic while preserving defaults.

Deliverables:

- Update `src/Controller/FeedController.php` to:
  - resolve user slot configuration,
  - load slot content from selected sources,
  - fallback each unconfigured/unavailable slot to default content.
- Refactor `src/View/layout_end.php` rendering to support generic slot payloads without changing security controls.

Checks:

- Default experience remains unchanged for users without preferences.
- `cisa_kev` is selectable and renders correctly.
- All dynamic output remains escaped with `Html::e()`.

#### Workstream 5: Test Coverage

Objective:

- Lock behavior and prevent regressions.

Deliverables:

- New tests in `tests/` for slot validation and fallback rules.
- Extend controller-focused tests for user settings submission and feed slot rendering decisions.

Suggested minimum assertions:

- Accept valid distinct source selections.
- Reject duplicate slot selections.
- Reject ineligible source IDs.
- Use defaults when no settings exist.
- Use defaults for any slot with unavailable configured source.

Checks:

- `php tests/run.php` passes.

#### Execution Order

1. Migration
2. Routes + controller methods
3. Settings UI
4. Feed integration
5. Tests and final verification

#### Definition Of Done For Phase 1

- All acceptance criteria in this document are satisfied.
- Migration applied locally without errors.
- Test suite passes.
- Manual smoke check confirms:
  - save + reload persists widget assignment,
  - fallback defaults still work,
  - no changes to public page widget behavior.

### Phase 2 (Polish)

- Enable same configurable widgets on other authenticated pages that already display widgets (for example starred view)
- Improve empty-state copy and quick links
- Optional per-slot item limit control (small bounded range)

### Phase 3 (Optional)

- Allow special adapters (`ransomlook`, `nvd`, `cisa_kev`) as selectable slot types with dedicated card templates
- Drag-and-drop slot ordering

## Acceptance Criteria

1. A logged-in user can select source assignments for slot 1 and slot 2 and save successfully.
2. Saved assignments persist across sessions.
3. Selected sources render in the right rail on My Feed.
4. If no assignments exist, current hardcoded widgets still render.
5. Duplicate source selection across both slots is rejected.
6. Invalid/ineligible source IDs are rejected server-side.
7. CSRF protection is enforced for settings updates.
8. Dynamic widget output remains escaped and safe.
9. Existing tests remain green and new feature tests pass.
10. `cisa_kev` sources are eligible for selection in v1.
11. Widget source selection is not constrained by the user's main feed source settings.

## Test Plan

- Unit tests for validation logic (eligible source, duplicate selection, invalid IDs).
- Controller tests for:
  - GET settings page rendering
  - POST success path
  - POST CSRF failure
  - POST invalid source handling
- Feed controller tests for:
  - configured slots path
  - default fallback path
  - unavailable source empty-state handling
- Basic render assertions for widget output escaping.

## Decisions (Locked)

1. Source eligibility in v1 includes `cisa_kev` in addition to `rss_atom` and `json_api`.
2. Slots are never hidden in v1. Each slot renders either custom source content or default content.
3. Widget selection is independent of user main-feed source toggles. Users can choose any eligible source.

## File Impact (Expected)

- `migrations/` new migration file
- `public/index.php` route registrations for widget settings
- `src/Controller/UserController.php`
- `src/Controller/FeedController.php`
- `src/View/user/` new settings template
- `src/View/layout_end.php`
- tests under `tests/`
