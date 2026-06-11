# Rule: outbound fetching (applies to src/Service, src/Adapter, bin)

- The ONLY way to make an outbound HTTP request is `Daybreak\Service\FeedFetcher`.
- FeedFetcher calls `SsrfGuard::assertSafe()` before connecting AND after every redirect.
- Never `curl`/`file_get_contents`/`fopen` a supplied URL directly.
- Send a realistic User-Agent — several sources (Cloudflare-fronted) reject non-browser
  clients (SPEC Appendix A note 1). Handle 202/403/429/5xx gracefully: record the error,
  increment consecutive_failures, mark degraded/auto_disabled — do NOT crash the run.
- Honour conditional GET (ETag / If-Modified-Since); store returned validators on the source.
- Enforce timeouts and the response-size cap. Cap redirects.
