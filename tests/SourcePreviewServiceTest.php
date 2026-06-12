<?php

declare(strict_types=1);

namespace Daybreak\Tests;

use Daybreak\Service\SourcePreviewService;

final class SourcePreviewServiceTest extends TestCase
{
    public function testPreviewRejectsUnsupportedAdapter(): void
    {
        $service = new SourcePreviewService(new FakeFetchClient([]));

        $preview = $service->preview([
            'adapter_type' => 'not_supported',
            'feed_url' => 'https://example.test/feed',
            'field_map' => null,
        ]);

        $this->assertFalse($preview['ok']);
        $this->assertSame('Unsupported adapter type selected.', $preview['error']);
    }

    public function testPreviewReturnsSampleItemsForRssAdapter(): void
    {
        $url = 'https://example.test/feed.xml';
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Example Feed</title>
    <item>
      <guid>1</guid>
      <title>Alpha</title>
      <link>https://example.test/a</link>
      <description>Summary A</description>
      <pubDate>Wed, 10 Jun 2026 08:00:00 +0000</pubDate>
    </item>
    <item>
      <guid>2</guid>
      <title>Beta</title>
      <link>https://example.test/b</link>
      <description>Summary B</description>
      <pubDate>Wed, 10 Jun 2026 09:00:00 +0000</pubDate>
    </item>
  </channel>
</rss>
XML;

        $service = new SourcePreviewService(new FakeFetchClient([
            $url => [
                'status' => 200,
                'body' => $xml,
                'etag' => null,
                'last_modified' => null,
                'not_modified' => false,
            ],
        ]));

        $preview = $service->preview([
            'adapter_type' => 'rss_atom',
            'feed_url' => $url,
            'etag' => null,
            'last_modified_hdr' => null,
            'field_map' => null,
        ]);

        $this->assertTrue($preview['ok']);
        $this->assertSame(200, $preview['http_status']);
        $this->assertSame(2, $preview['items_count']);
        $this->assertCount(2, $preview['sample_items']);
    }
}
