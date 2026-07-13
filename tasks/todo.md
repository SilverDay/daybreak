# Daybreak Task Tracker

## Phase 1: User-Assigned Sidebar Widgets

- [x] Create migration `019_user_widget_sources.sql`
- [x] Register routes `GET/POST /settings/widgets` in `public/index.php`
- [x] Implement `UserController::showWidgets()`
- [x] Implement `UserController::handleWidgets()` with CSRF and validation
- [x] Add settings nav link for Widgets in `src/View/settings_layout.php`
- [x] Create `src/View/user/widgets.php` with two selectors and CSRF token
- [x] Add slot preference loading/fallback logic in `src/Controller/FeedController.php`
- [x] Refactor `src/View/layout_end.php` for slot-based widget rendering
- [x] Add/extend tests for validation, fallback, and persistence behavior
- [x] Run `php tests/run.php` and fix any regressions
- [x] Run manual smoke check for settings save + feed rendering
