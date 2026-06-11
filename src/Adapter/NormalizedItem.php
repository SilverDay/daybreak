<?php
declare(strict_types=1);

namespace Daybreak\Adapter;

/** One normalised article from any adapter. */
final class NormalizedItem
{
    public function __construct(
        public string $guid,            // stable per-source id (feed guid or hashed url)
        public string $title,
        public string $url,             // outbound article link
        public ?string $summary = null, // feed-provided, already sanitised, may be null
        public ?\DateTimeImmutable $publishedAt = null,
    ) {}
}
