# Rule: writing a SourceAdapter

A new source type implements `Daybreak\Adapter\SourceAdapter`:
- `supports(string $adapterType): bool` — match your `adapter_type` enum value.
- `fetch(array $source, FeedFetcher $fetcher): FetchResult` — fetch via `$fetcher`,
  parse, return `NormalizedItem[]` (guid, title, url, summary|null, publishedAt|null).
- Sanitise summaries with `Html::sanitizeSummary()`. Derive a stable `guid`
  (feed guid, else `hash('sha256', $url)`).
- Honour `not_modified` (304) — return an empty FetchResult with `notModified=true`.
- Register the adapter in `AggregationService::__construct()`.
- Add the enum value to the `sources.adapter_type` ENUM via a NEW migration.
