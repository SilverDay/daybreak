<?php

declare(strict_types=1);

namespace Daybreak\Adapter;

use Daybreak\Service\FetchClient;

/**
 * Contract every source type implements. Adapters never fetch directly —
 * they use the injected FeedFetcher (SSRF-guarded). See .claude/rules/adapters.md.
 */
interface SourceAdapter
{
    public function supports(string $adapterType): bool;

    /**
     * @param array{id:int,feed_url:?string,etag:?string,last_modified_hdr:?string,field_map:?string} $source
     */
    public function fetch(array $source, FetchClient $fetcher): FetchResult;
}
