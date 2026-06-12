<?php

declare(strict_types=1);

namespace Daybreak\Tests;

use Daybreak\Adapter\RansomlookAdapter;

final class RansomlookAdapterTest extends TestCase
{
    public function testFetchMapsVictimAndGroupIntoTitle(): void
    {
        $url = 'https://www.ransomlook.io/api/recent';
        $fetcher = new FakeFetchClient([
            $url => [
                'status' => 200,
                'body' => json_encode([
                    [
                        'group_name' => 'BlackBasta',
                        'post_title' => 'example.org',
                        'discovered' => '2026-06-10 08:00:00',
                        'link' => '/post/example',
                    ],
                ], JSON_THROW_ON_ERROR),
                'etag' => null,
                'last_modified' => null,
                'not_modified' => false,
            ],
        ]);

        $adapter = new RansomlookAdapter();
        $result = $adapter->fetch([
            'feed_url' => $url,
        ], $fetcher);

        $this->assertCount(1, $result->items);
        $this->assertSame('BlackBasta: example.org', $result->items[0]->title);
        $this->assertSame('https://www.ransomlook.io/post/example', $result->items[0]->url);
        $this->assertNull($result->items[0]->summary);
        $this->assertSame('2026-06-10 08:00:00', $result->items[0]->publishedAt?->format('Y-m-d H:i:s'));
    }

    public function testFetchFallsBackToRecentPageForMissingLink(): void
    {
        $url = 'https://www.ransomlook.io/api/recent';
        $fetcher = new FakeFetchClient([
            $url => [
                'status' => 200,
                'body' => json_encode([
                    [
                        'group_name' => '',
                        'post_title' => 'example.net',
                        'discovered' => '',
                        'link' => '',
                    ],
                    [
                        'group_name' => '',
                        'post_title' => '',
                        'discovered' => '',
                        'link' => '',
                    ],
                ], JSON_THROW_ON_ERROR),
                'etag' => null,
                'last_modified' => null,
                'not_modified' => false,
            ],
        ]);

        $adapter = new RansomlookAdapter();
        $result = $adapter->fetch([
            'feed_url' => $url,
        ], $fetcher);

        $this->assertCount(1, $result->items);
        $this->assertSame('example.net', $result->items[0]->title);
        $this->assertSame('https://www.ransomlook.io/recent', $result->items[0]->url);
        $this->assertNull($result->items[0]->publishedAt);
    }

    public function testFetchPreservesAbsoluteLinks(): void
    {
        $url = 'https://www.ransomlook.io/api/recent';
        $fetcher = new FakeFetchClient([
            $url => [
                'status' => 200,
                'body' => json_encode([
                    [
                        'group_name' => 'Lockbit',
                        'post_title' => 'corp.example',
                        'discovered' => '',
                        'link' => 'https://www.ransomlook.io/post/corp-example',
                    ],
                ], JSON_THROW_ON_ERROR),
                'etag' => null,
                'last_modified' => null,
                'not_modified' => false,
            ],
        ]);

        $adapter = new RansomlookAdapter();
        $result = $adapter->fetch([
            'feed_url' => $url,
        ], $fetcher);

        $this->assertCount(1, $result->items);
        $this->assertSame('https://www.ransomlook.io/post/corp-example', $result->items[0]->url);
    }

    public function testFetchNormalizesRelativeLinksWithoutLeadingSlash(): void
    {
        $url = 'https://www.ransomlook.io/api/recent';
        $fetcher = new FakeFetchClient([
            $url => [
                'status' => 200,
                'body' => json_encode([
                    [
                        'group_name' => '8base',
                        'post_title' => 'target.example',
                        'discovered' => '',
                        'link' => 'post/target-example',
                    ],
                ], JSON_THROW_ON_ERROR),
                'etag' => null,
                'last_modified' => null,
                'not_modified' => false,
            ],
        ]);

        $adapter = new RansomlookAdapter();
        $result = $adapter->fetch([
            'feed_url' => $url,
        ], $fetcher);

        $this->assertCount(1, $result->items);
        $this->assertSame('https://www.ransomlook.io/post/target-example', $result->items[0]->url);
    }

    public function testFetchMapsLegacyBlogLinksToRecentFallback(): void
    {
        $url = 'https://www.ransomlook.io/api/recent';
        $fetcher = new FakeFetchClient([
            $url => [
                'status' => 200,
                'body' => json_encode([
                    [
                        'group_name' => 'legacy',
                        'post_title' => 'victim.example',
                        'discovered' => '',
                        'link' => '/blog/dead-link',
                    ],
                ], JSON_THROW_ON_ERROR),
                'etag' => null,
                'last_modified' => null,
                'not_modified' => false,
            ],
        ]);

        $adapter = new RansomlookAdapter();
        $result = $adapter->fetch([
            'feed_url' => $url,
        ], $fetcher);

        $this->assertCount(1, $result->items);
        $this->assertSame('https://www.ransomlook.io/recent', $result->items[0]->url);
    }

    public function testFetchMapsLegacyCompanyLinksToRecentFallback(): void
    {
        $url = 'https://www.ransomlook.io/api/recent';
        $fetcher = new FakeFetchClient([
            $url => [
                'status' => 200,
                'body' => json_encode([
                    [
                        'group_name' => 'legacy',
                        'post_title' => 'victim.example',
                        'discovered' => '',
                        'link' => 'https://www.ransomlook.io/Company/TVG/',
                    ],
                ], JSON_THROW_ON_ERROR),
                'etag' => null,
                'last_modified' => null,
                'not_modified' => false,
            ],
        ]);

        $adapter = new RansomlookAdapter();
        $result = $adapter->fetch([
            'feed_url' => $url,
        ], $fetcher);

        $this->assertCount(1, $result->items);
        $this->assertSame('https://www.ransomlook.io/recent', $result->items[0]->url);
    }
}
