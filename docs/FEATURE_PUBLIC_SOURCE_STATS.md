# Daybreak Feature: Public Source Statistics

## Summary

Add a new public page that gives visitors a transparent view into the sources behind Daybreak.
The page should list all public sources, show how much each source contributes, and expose a few fun but useful activity statistics.

This document is now the implementation-ready feature spec for the first delivery slices of the feature.

## Goals

- Make the source base visible and trustworthy for public users.
- Show which sources are most active over time.
- Turn the source list into a lightweight observability page for content volume and freshness.
- Add a few playful statistics that make the page interesting to browse, not just informational.

## Proposed Route

- Public route: `/sources`
- Page title: `Sources`
- Suggested nav label: `Sources`

## Scope For The First Build

The first build should cover only Phase 1 and Phase 2.

Included now:

- New public `GET /sources` page
- Public source directory with aggregated article counts
- Summary cards at the top of the page
- Recent activity counts for `24h`, `7d`, and `30d`
- Sorting and filtering controls
- Supporting stats sections for most-active and freshness views

Explicitly deferred:

- Per-source daily heatmap or day-grid
- Consistency score
- Burstiness and quiet-reliability metrics
- Category-distribution analytics beyond simple directory filters
- Source-specific public detail pages

## Primary User Value

- Visitors can understand where the feed content comes from.
- Visitors can judge coverage breadth across categories and sources.
- Operators gain a public-facing transparency page without exposing admin-only health details.

## Core Page Sections

### 1. Source Directory

List all public-facing sources in a table or card grid.

Phase priority: Phase 1

Suggested fields:

- Source name
- Category
- Adapter type where meaningful for transparency, if the wording is user-friendly
- Total published articles stored in Daybreak
- Articles in last 24h
- Articles in last 7 days
- Articles in last 30 days
- Date of most recent article
- Link to source homepage if available

Suggested sorting options:

- Most articles overall
- Most active in last 7 days
- Recently updated
- Alphabetical

Suggested filters:

- Category
- Time window
- Search by source name

Implementation note:

- Phase 1 can ship with category filter only.
- Phase 2 adds time-window-aware sorting and search-by-name.

### 2. Articles Per Day Per Source

Show a per-source daily activity breakdown for a recent window.

Phase priority: Deferred to Phase 3

Suggested default window:

- Last 14 days

Possible display options:

- Compact table with one row per source and one column per day
- Horizontal heatmap-style grid
- Mini sparkline-like bars using plain CSS

Why it is useful:

- Shows which sources publish steadily versus in bursts
- Makes outages or stale sources visible without exposing internal error data
- Helps users spot high-volume versus high-signal sources

## Additional Statistics Worth Showing

These are good candidates because they are interesting to users and can be derived from article data without exposing private system details.

### 3. Most Active Sources

Phase priority: Phase 2

- Top 10 sources by article count in last 7 days
- Top 10 sources by article count in last 30 days

### 4. Freshness Leaders

Phase priority: Phase 2

- Sources with the most recent publication timestamps
- Sources active today
- Sources inactive for more than 7 days

### 5. Consistency Score

Phase priority: Deferred to Phase 3

Simple public metric based on publishing regularity, for example:

- Number of active days in last 30 days
- Average articles per active day
- Longest active streak in last 30 days

This gives a better picture than raw volume alone.

### 6. Coverage By Category

Phase priority: Phase 3 or later

- Number of sources per category
- Number of articles per category in last 7 or 30 days
- Share of total article volume by category

This helps explain where Daybreak is strongest or thinnest.

### 7. Newest Sources

Phase priority: Optional Phase 2.5

- Most recently added sources
- Optionally show when they first produced an article in Daybreak

### 8. Archive Depth

Phase priority: Deferred to Phase 3

- Oldest article date per source
- Total days covered per source
- First seen / latest seen article window

This gives users a sense of historical depth.

### 9. Quiet But Reliable Sources

Phase priority: Deferred to Phase 3

Highlight sources that publish infrequently but consistently.

Possible definition:

- Low total volume
- Activity spread across many separate days

This can surface niche but dependable sources.

### 10. Burstiest Sources

Phase priority: Deferred to Phase 3

Highlight sources with the biggest single-day spikes.

Possible metric:

- Highest article count on any one day in the last 30 days

This is mostly a fun statistic, but it also reveals event-driven outlets.

## Public vs Private Boundary

This page should remain public and safe to expose.

Should show:

- Article counts
- Activity windows
- Freshness and consistency derived from published article timestamps
- Source names, categories, and public URLs

Should not show:

- Internal fetch errors
- HTTP status codes
- Failure streaks
- Admin-only health flags
- Anything tied to individual users or personal feed preferences

## Suggested Data Rules

- Only include sources that are intended to be visible on the public site.
- Exclude purely internal or disabled sources unless there is a deliberate product decision to show them.
- Use stored article data only; do not fetch live during page load.
- Prefer aggregated queries over row-by-row calculations.

More specific rules for implementation:

- Use `articles` as the only content-volume source.
- Join source metadata from `sources` and category metadata from `source_categories`.
- Restrict to public content adapters only unless there is a product reason to surface widgets separately.
- Do not include user-specific state or personalized feed preferences.
- Do not expose administrative health columns such as failure streaks or fetch status.

## Suggested UX Approach

- Keep the top of the page simple: summary stats plus a clean source table.
- Put more playful or dense analytics further down the page.
- Make the page readable without charts first; charts are optional.
- Ensure all stats degrade cleanly on mobile.

Suggested top summary cards:

- Total public sources
- Total stored articles
- Articles in last 24h
- Active sources in last 7 days

Suggested initial page order:

1. Intro copy and summary cards
2. Source directory with sorting/filter controls
3. Most active sources section
4. Freshness section
5. Deferred analytics placeholder or nothing at all until Phase 3

## Potential SEO Value

This page could also help SEO if done cleanly.

Potential benefits:

- A crawlable source directory page
- Useful long-tail relevance around source names and categories
- Additional internal linking to category pages

Suggested metadata theme:

- Title: `Sources · Daybreak`
- Description: `Browse all security news sources tracked by Daybreak, including activity, freshness, and publishing trends.`

## Performance Considerations

- Daily per-source breakdowns can become query-heavy as the archive grows.
- Consider pre-aggregating daily counts later if needed.
- Default to a limited time window such as 14 or 30 days.
- Avoid rendering very wide daily tables on small screens without horizontal scrolling or a compact alternative.

Phase 1 and 2 performance target:

- Prefer one summary query plus one aggregated source listing query.
- Avoid per-source subqueries in the render loop.
- Keep default listing size bounded if the source count grows significantly.

## Data Model And Query Outline

The feature should be built from cached database content only.

Likely tables:

- `sources`
- `articles`
- `source_categories`

Likely public directory fields per source:

- `sources.id`
- `sources.name`
- `sources.url`
- `sources.adapter_type`
- `sources.status`
- `source_categories.name`
- `source_categories.slug`
- `COUNT(articles.id)` as total article count
- `MAX(articles.published_at)` as latest article timestamp
- Conditional counts for last `24h`, `7d`, and `30d`

Suggested aggregation approach:

- One query for top summary cards
- One grouped source query for the directory
- Optional focused grouped queries for top-active and freshness side sections if the main query becomes awkward

## Implementation Shape

Likely backend additions:

- New public controller action for `/sources`
- New public view template for the source statistics page
- Shared layout nav link for `Sources`

Likely files for Phase 1 and 2:

- `public/index.php`
- `src/Controller/PublicController.php` or a new dedicated controller if the public controller becomes too crowded
- `src/View/layout.php`
- `src/View/page/` or a new `src/View/sources/` template

Preferred controller behavior:

- Set SEO title and description for the page
- Query cached aggregate data only
- Render a public page through the existing layout flow

## File-By-File Build Checklist

Use this as the execution order for implementation.

### 1. `public/index.php`

Add the new public route:

- `GET /sources` → public source statistics page

Checklist:

- Register the route near the other public routes
- Keep the route read-only
- Do not add any fetch or admin coupling here

### 2. `src/Controller/PublicController.php`

Add a new action, recommended name:

- `sources(array $args = []): void`

Checklist:

- Validate allowlisted query parameters
- Build category data for filters
- Run one summary query
- Run one grouped source-directory query
- In Phase 2, run one or two additional focused aggregate queries for top-active and freshness sections if needed
- Set public SEO metadata
- Render through the shared layout

Recommended request parameters:

- `category`: category slug, optional
- `q`: source-name search string, optional, max 100 chars
- `sort`: one of `total`, `recent_24h`, `recent_7d`, `recent_30d`, `latest`, `name`

Recommended defaults:

- `category = null`
- `q = ''`
- `sort = 'total'`

Validation rules:

- Category slug must match a known category slug or be ignored/rejected consistently
- Search term should be trimmed and length-limited
- Sort must be allowlisted and never interpolated directly from raw input

### 3. `src/View/layout.php`

Add a public navigation link:

- Label: `Sources`
- URL: `/sources`

Checklist:

- Show the link for both guests and signed-in users
- Add active-nav support if desired, for example `activeNav = 'sources'`
- Keep the existing header behavior intact

### 4. New public template

Recommended location:

- `src/View/page/sources.php`

Alternative if you want clearer separation:

- `src/View/sources/index.php`

Checklist:

- Render intro copy
- Render summary cards
- Render filter controls
- Render source directory table or stacked mobile cards
- Render “Most Active Sources” section
- Render “Freshness Leaders” section
- Render empty states for no sources or no filter matches

### 5. Tests

Recommended additions:

- New controller-level test file for public source stats request validation
- Optional rendering-level coverage if there is already a test pattern for public pages

Suggested location:

- `tests/PublicControllerTest.php`

## Request And Controller Contract

The controller should normalize all request state before querying.

Recommended normalized inputs:

- `$activeCategory`: `?string`
- `$searchQuery`: `string`
- `$sortKey`: `string`

Recommended controller output variables for the template:

- `$title`
- `$seoTitle`
- `$seoDescription`
- `$activeNav`
- `$showWidgets`
- `$categories`
- `$activeCategory`
- `$searchQuery`
- `$sortKey`
- `$summary`
- `$sources`
- `$mostActive7d`
- `$mostActive30d`
- `$freshnessLeaders`
- `$activeToday`
- `$staleSources`

Recommended shapes:

### `$summary`

```php
[
	'total_sources' => 0,
	'total_articles' => 0,
	'articles_24h' => 0,
	'active_sources_7d' => 0,
]
```

### `$sources[]`

```php
[
	'id' => 0,
	'name' => '',
	'url' => '',
	'adapter_type' => '',
	'status' => '',
	'category_name' => null,
	'category_slug' => null,
	'total_articles' => 0,
	'articles_24h' => 0,
	'articles_7d' => 0,
	'articles_30d' => 0,
	'latest_article_at' => null,
]
```

### `$mostActive7d[]` and `$mostActive30d[]`

```php
[
	'source_name' => '',
	'category_name' => null,
	'article_count' => 0,
]
```

### `$freshnessLeaders[]`

```php
[
	'source_name' => '',
	'category_name' => null,
	'latest_article_at' => null,
]
```

## SQL Outline

All queries must go through `Database::query($sql, $params)` with bound parameters.

### Public source eligibility baseline

Recommended baseline filter:

- `s.status IN ('active', 'degraded')`
- `s.adapter_type IN ('rss_atom', 'json_api')`

This keeps the page aligned with the main public feed and excludes widget-only adapters unless product requirements change.

### Phase 1 summary query outline

Purpose:

- total eligible sources
- total stored articles across eligible sources
- articles published in last 24h
- number of eligible sources with at least one article in last 7d

Query shape:

```sql
SELECT
	COUNT(DISTINCT s.id) AS total_sources,
	COUNT(a.id) AS total_articles,
	SUM(CASE WHEN a.published_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) AS articles_24h,
	COUNT(DISTINCT CASE
		WHEN a.published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN s.id
		ELSE NULL
	END) AS active_sources_7d
FROM sources s
LEFT JOIN articles a ON a.source_id = s.id
WHERE s.status IN ('active', 'degraded')
  AND s.adapter_type IN ('rss_atom', 'json_api')
```
```

### Phase 1 and 2 source-directory query outline

Purpose:

- public source directory rows
- total and recent article counts
- latest article date
- category data

Base query shape:

```sql
SELECT
	s.id,
	s.name,
	s.url,
	s.adapter_type,
	s.status,
	c.name AS category_name,
	c.slug AS category_slug,
	COUNT(a.id) AS total_articles,
	SUM(CASE WHEN a.published_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) AS articles_24h,
	SUM(CASE WHEN a.published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS articles_7d,
	SUM(CASE WHEN a.published_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS articles_30d,
	MAX(a.published_at) AS latest_article_at
FROM sources s
LEFT JOIN source_categories c ON c.id = s.category_id
LEFT JOIN articles a ON a.source_id = s.id
WHERE s.status IN ('active', 'degraded')
  AND s.adapter_type IN ('rss_atom', 'json_api')
```
```

Optional filters:

- category filter: `AND c.slug = ?`
- source-name search: `AND s.name LIKE ?`

Required grouping:

- `GROUP BY s.id, s.name, s.url, s.adapter_type, s.status, c.name, c.slug`

Allowlisted ordering map:

- `total` → `total_articles DESC, s.name ASC`
- `recent_24h` → `articles_24h DESC, total_articles DESC, s.name ASC`
- `recent_7d` → `articles_7d DESC, total_articles DESC, s.name ASC`
- `recent_30d` → `articles_30d DESC, total_articles DESC, s.name ASC`
- `latest` → `latest_article_at DESC, total_articles DESC, s.name ASC`
- `name` → `s.name ASC`

Implementation rule:

- Build `ORDER BY` from a fixed PHP map, not from raw user input

### Phase 2 most-active query outline

Purpose:

- top 10 sources by 7-day count
- top 10 sources by 30-day count

Approach:

- Either reuse the grouped directory result if the full page already contains all sources
- Or run focused top-10 grouped queries with the same source-eligibility filter

### Phase 2 freshness query outline

Purpose:

- recently updated sources
- sources active today
- optionally stale sources with no articles in the last 7 days

Approach:

- use `MAX(a.published_at)` grouped by source
- stale means `latest_article_at < DATE_SUB(NOW(), INTERVAL 7 DAY)` or `NULL` if zero-article sources are included

## Build Order By Commit Slice

### Slice A: Route And Page Skeleton

- add route
- add controller action returning hard-coded placeholder data structure
- add template wired into shared layout
- add nav link

Success check:

- `/sources` renders without errors

### Slice B: Phase 1 Aggregates

- replace placeholder data with summary query and base grouped source query
- render source directory and summary cards

Success check:

- page renders live cached stats from the database

### Slice C: Phase 2 Filters And Sorting

- add normalized request parsing
- add category filter, search, sort mapping
- update directory rendering to show 24h, 7d, and 30d counts

Success check:

- filters and sorting behave predictably and safely

### Slice D: Phase 2 Supporting Sections

- add most-active and freshness sections
- finalize empty states and mobile presentation

Success check:

- the page is useful without requiring Phase 3 analytics

## Test Checklist

Minimum recommended tests:

### Controller request normalization

- invalid category slug is ignored or handled consistently
- invalid sort falls back to default sort
- oversized search query is trimmed or capped

### Query-driven behavior

- zero-source or zero-article states do not crash the page
- sources with no articles are handled according to the chosen product rule
- recent-window counts are displayed consistently

### Public safety

- no private health fields are rendered
- no live fetch behavior is introduced

### Suggested test cases

- `testSourcesSortFallsBackToTotal`
- `testSourcesRejectsUnknownCategory`
- `testSourcesSearchQueryIsLengthLimited`
- `testSourcesPageRendersWithNoArticles`

## Done Definition For Phase 1 And 2

Phase 1 and Phase 2 are complete when:

- `/sources` is reachable from the public navigation
- source directory data comes entirely from cached DB content
- summary cards, recent counts, sorting, and filtering work
- most-active and freshness sections are present
- mobile layout is acceptable
- focused tests exist for request validation and empty-state behavior
- PHP lint and `php tests/run.php` pass

## Detailed Phase Breakdown

### Phase 1: Basic Public Source Directory

Goal:

- Ship a useful transparency page with low implementation risk.

Required output:

- Public `/sources` route
- Summary cards for total sources, total articles, articles in last 24h, active sources in last 7d
- Source list with source name, category, total article count, latest article date, and source homepage link
- Default sort by total article count descending

Acceptance criteria:

- Page renders without any live fetches
- Only public-safe source and article aggregates are shown
- Sources with zero stored articles are handled deliberately, either shown with zero counts or excluded by a documented rule
- Page works on mobile and desktop

### Phase 2: Time-Window Activity Stats

Goal:

- Make the page analytically useful, not just a directory.

Required output:

- Add `24h`, `7d`, and `30d` counts to each source row
- Add category filter
- Add source-name search
- Add sorting by recent activity and latest update time
- Add “Most Active Sources” and “Freshness Leaders” sections

Acceptance criteria:

- Users can re-order sources by recent activity without a full redesign
- Recent-count metrics match the chosen time-window logic consistently
- Freshness section clearly identifies active and stale sources without exposing internal errors

### Phase 3: Daily Breakdown And Fun Analytics

Goal:

- Add the more playful and denser analytical layer after the core page is stable.

Required output:

- Per-source per-day activity grid or equivalent compact visualization
- Consistency, burstiness, and quiet-reliability metrics
- Optional category distribution analytics

## Open Product Questions

- Should disabled or degraded sources appear if they have historical public articles?
- Should source rows link to a source-specific public archive page later?
- Should the page focus on all-time metrics, recent-window metrics, or both equally?
- Should category totals be based on current source category only, or historical category state at article ingestion time?

## Acceptance Criteria

- A new feature spec exists for a public source statistics page.
- The spec covers the user-requested source list and per-day article counts.
- The spec proposes additional public-friendly statistics that are interesting but safe to expose.
- The spec preserves the Daybreak rule that public pages read cached data only and do not fetch live sources.
- The spec clearly identifies Phase 1 and Phase 2 as the recommended starting implementation slices.
- The spec names the likely route, data sources, deliverables, and acceptance criteria for those phases.
