<?php
declare(strict_types=1);

namespace Daybreak\Adapter;

/** Result of one fetch attempt. */
final class FetchResult
{
    /** @param NormalizedItem[] $items */
    public function __construct(
        public array $items = [],
        public int $httpStatus = 0,
        public ?string $etag = null,
        public ?string $lastModified = null,
        public bool $notModified = false,
    ) {}
}
