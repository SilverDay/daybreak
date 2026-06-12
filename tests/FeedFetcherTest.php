<?php

declare(strict_types=1);

namespace Daybreak\Tests;

use Daybreak\Service\FeedFetcher;
use ReflectionMethod;

final class FeedFetcherTest extends TestCase
{
    public function testResolveRedirectKeepsSiblingPath(): void
    {
        $fetcher = new FeedFetcher();
        $method  = new ReflectionMethod(FeedFetcher::class, 'resolveRedirect');
        $method->setAccessible(true);

        $resolved = $method->invoke($fetcher, 'https://example.com/feeds/security/index.xml', 'daily.xml');

        $this->assertSame('https://example.com/feeds/security/daily.xml', $resolved);
    }

    public function testResolveRedirectNormalizesParentSegments(): void
    {
        $fetcher = new FeedFetcher();
        $method  = new ReflectionMethod(FeedFetcher::class, 'resolveRedirect');
        $method->setAccessible(true);

        $resolved = $method->invoke($fetcher, 'https://example.com/feeds/security/index.xml', '../top.xml?x=1');

        $this->assertSame('https://example.com/feeds/top.xml?x=1', $resolved);
    }

    public function testResolveRedirectPreservesAbsoluteUrls(): void
    {
        $fetcher = new FeedFetcher();
        $method  = new ReflectionMethod(FeedFetcher::class, 'resolveRedirect');
        $method->setAccessible(true);

        $resolved = $method->invoke($fetcher, 'https://example.com/feeds/security/index.xml', 'https://cdn.example.net/feed.xml');

        $this->assertSame('https://cdn.example.net/feed.xml', $resolved);
    }
}
