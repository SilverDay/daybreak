# /new-adapter

Scaffold a new source adapter following `.claude/rules/adapters.md`.

## Usage
`/new-adapter <Name> <adapter_type>`  e.g. `/new-adapter Nvd nvd`

## What to generate
1. `src/Adapter/<Name>Adapter.php` implementing `SourceAdapter`:
   - `declare(strict_types=1);`, namespace `Daybreak\Adapter`.
   - `supports()` returns true for `<adapter_type>`.
   - `fetch()` uses the injected `FeedFetcher`, returns `FetchResult` of `NormalizedItem[]`.
   - Sanitise summaries; stable guid; honour 304.
2. Register it in `AggregationService::__construct()` (`$this->adapters[]`).
3. If `<adapter_type>` is new, add a migration extending the `sources.adapter_type` ENUM.
4. Note any source-specific quirks (auth, rate limits, bot-blocking) in a comment.
