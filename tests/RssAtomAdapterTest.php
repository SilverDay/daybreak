<?php

declare(strict_types=1);

namespace Daybreak\Tests;

use Daybreak\Adapter\RssAtomAdapter;

final class RssAtomAdapterTest extends TestCase
{
    public function testFetchParsesRssItems(): void
    {
        $url = 'https://example.test/feed.xml';
        $feed = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <guid>item-1</guid>
      <title><![CDATA[<b>Security</b> update]]></title>
      <link>https://example.test/story-1</link>
      <description><![CDATA[<p>Patch <strong>now</strong></p>]]></description>
      <pubDate>Wed, 10 Jun 2026 08:00:00 +0000</pubDate>
    </item>
  </channel>
</rss>
XML;
        $fetcher = new FakeFetchClient([
            $url => [
                'status' => 200,
                'body' => $feed,
                'etag' => 'rss-1',
                'last_modified' => 'Wed, 10 Jun 2026 08:00:00 GMT',
                'not_modified' => false,
            ],
        ]);

        $adapter = new RssAtomAdapter();
        $result = $adapter->fetch([
            'feed_url' => $url,
            'etag' => null,
            'last_modified_hdr' => null,
        ], $fetcher);

        $this->assertCount(1, $result->items);
        $this->assertSame('item-1', $result->items[0]->guid);
        $this->assertSame('Security update', $result->items[0]->title);
        $this->assertSame('Patch now', $result->items[0]->summary);
    }

    public function testFetchReturnsNotModifiedResult(): void
    {
        $url = 'https://example.test/feed.xml';
        $fetcher = new FakeFetchClient([
            $url => [
                'status' => 304,
                'body' => '',
                'etag' => 'rss-2',
                'last_modified' => 'Wed, 10 Jun 2026 08:00:00 GMT',
                'not_modified' => true,
            ],
        ]);

        $adapter = new RssAtomAdapter();
        $result = $adapter->fetch([
            'feed_url' => $url,
            'etag' => 'rss-1',
            'last_modified_hdr' => 'Tue, 09 Jun 2026 08:00:00 GMT',
        ], $fetcher);

        $this->assertTrue($result->notModified);
        $this->assertCount(0, $result->items);
    }
}
