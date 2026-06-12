<?php

declare(strict_types=1);

namespace Daybreak\Tests;

use Daybreak\Adapter\JsonApiAdapter;

final class JsonApiAdapterTest extends TestCase
{
    public function testFetchMapsConfiguredFieldsAndSanitizesSummary(): void
    {
        $url = 'https://example.test/api/feed';
        $fetcher = new FakeFetchClient([
            $url => [
                'status' => 200,
                'body' => json_encode([
                    'items' => [[
                        'uuid' => 'story-1',
                        'headline' => '<b>Incident</b> update',
                        'link' => 'https://example.test/story-1',
                        'body' => '<p>Alert <script>bad()</script> now</p>',
                        'published' => '2026-06-10T08:00:00+00:00',
                    ]],
                ], JSON_THROW_ON_ERROR),
                'etag' => 'abc',
                'last_modified' => 'Wed, 10 Jun 2026 08:00:00 GMT',
                'not_modified' => false,
            ],
        ]);

        $adapter = new JsonApiAdapter();
        $result = $adapter->fetch([
            'feed_url' => $url,
            'etag' => null,
            'last_modified_hdr' => null,
            'field_map' => json_encode([
                'items_path' => 'items',
                'guid' => 'uuid',
                'title' => 'headline',
                'url' => 'link',
                'summary' => 'body',
                'published_at' => 'published',
            ], JSON_THROW_ON_ERROR),
        ], $fetcher);

        $this->assertCount(1, $result->items);
        $this->assertSame('story-1', $result->items[0]->guid);
        $this->assertSame('Incident update', $result->items[0]->title);
        $this->assertSame('Alert bad() now', $result->items[0]->summary);
        $this->assertSame('2026-06-10 08:00:00', $result->items[0]->publishedAt?->format('Y-m-d H:i:s'));
    }

    public function testFetchHonorsNotModified(): void
    {
        $url = 'https://example.test/api/feed';
        $fetcher = new FakeFetchClient([
            $url => [
                'status' => 304,
                'body' => '',
                'etag' => 'etag-2',
                'last_modified' => 'Wed, 10 Jun 2026 08:00:00 GMT',
                'not_modified' => true,
            ],
        ]);

        $adapter = new JsonApiAdapter();
        $result = $adapter->fetch([
            'feed_url' => $url,
            'etag' => 'etag-1',
            'last_modified_hdr' => 'Tue, 09 Jun 2026 08:00:00 GMT',
            'field_map' => null,
        ], $fetcher);

        $this->assertTrue($result->notModified);
        $this->assertCount(0, $result->items);
        $this->assertSame('etag-1', $fetcher->calls[0]['etag']);
    }

    public function testPreviewWarnsWhenConfiguredPathDoesNotExist(): void
    {
        $url = 'https://example.test/api/feed';
        $fetcher = new FakeFetchClient([
            $url => [
                'status' => 200,
                'body' => json_encode([
                    'items' => [[
                        'id' => 'story-1',
                        'title' => 'Story',
                        'url' => 'https://example.test/story-1',
                    ]],
                ], JSON_THROW_ON_ERROR),
                'etag' => null,
                'last_modified' => null,
                'not_modified' => false,
            ],
        ]);

        $adapter = new JsonApiAdapter();
        $preview = $adapter->preview([
            'feed_url' => $url,
            'etag' => null,
            'last_modified_hdr' => null,
            'field_map' => json_encode([
                'items_path' => 'items',
                'title' => 'headline',
                'url' => 'url',
            ], JSON_THROW_ON_ERROR),
        ], $fetcher);

        $this->assertCount(0, $preview['errors']);
        $this->assertTrue(count($preview['warnings']) >= 1);
        $this->assertCount(1, $preview['result']->items);
    }

    public function testPreviewReturnsErrorForMissingItemsPath(): void
    {
        $url = 'https://example.test/api/feed';
        $fetcher = new FakeFetchClient([
            $url => [
                'status' => 200,
                'body' => json_encode(['items' => []], JSON_THROW_ON_ERROR),
                'etag' => null,
                'last_modified' => null,
                'not_modified' => false,
            ],
        ]);

        $adapter = new JsonApiAdapter();
        $preview = $adapter->preview([
            'feed_url' => $url,
            'etag' => null,
            'last_modified_hdr' => null,
            'field_map' => json_encode(['items_path' => 'payload.articles'], JSON_THROW_ON_ERROR),
        ], $fetcher);

        $this->assertCount(1, $preview['errors']);
        $this->assertCount(0, $preview['result']->items);
    }
}
