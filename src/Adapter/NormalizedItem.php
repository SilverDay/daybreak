<?php
declare(strict_types=1);

namespace Daybreak\Adapter;

/** One normalised article from any adapter. */
final class NormalizedItem
{
    public ?\DateTimeImmutable $publishedAt;

    public function __construct(
        public string $guid,            // stable per-source id (feed guid or hashed url)
        public string $title,
        public string $url,             // outbound article link
        public ?string $summary = null, // feed-provided, already sanitised, may be null
        ?\DateTimeImmutable $publishedAt = null,
    ) {
        // Feeds report dates in their own offset (e.g. -0400); DateTimeImmutable keeps
        // that wall-clock value verbatim on format(), so without converting to UTC here,
        // articles from differently-offset feeds compare/sort incorrectly once stored
        // as plain DATETIME strings.
        $this->publishedAt = $publishedAt?->setTimezone(new \DateTimeZone('UTC'));
    }
}
