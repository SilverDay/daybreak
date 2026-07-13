<?php

declare(strict_types=1);

namespace Daybreak\Tests;

use Daybreak\Controller\PageController;
use ReflectionMethod;

final class PageControllerTest extends TestCase
{
    public function testRobotsIncludesValidSitemapDirective(): void
    {
        $controller = new PageController();
        $baseUrl = $this->invokePrivateString($controller, 'baseUrl');

        ob_start();
        $controller->robots();
        $robots = (string) ob_get_clean();

        $expectedSitemap = $baseUrl !== ''
            ? rtrim($baseUrl, '/') . '/sitemap.xml'
            : '/sitemap.xml';

        $this->assertStringContains("User-agent: *\n", $robots);
        $this->assertStringContains("Allow: /\n", $robots);
        $this->assertStringContains('Sitemap: ' . $expectedSitemap . "\n", $robots);
    }

    public function testSitemapXmlIsWellFormedAndHasExpectedUrls(): void
    {
        $controller = new PageController();
        $baseUrl = $this->invokePrivateString($controller, 'baseUrl');

        ob_start();
        $controller->sitemap();
        $xml = (string) ob_get_clean();

        $parsed = simplexml_load_string($xml);
        $this->assertNotNull($parsed, 'Expected valid sitemap XML.');

        $parsed->registerXPathNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $locNodes = $parsed->xpath('//sm:url/sm:loc');
        if ($locNodes === false) {
            $this->fail('Failed to evaluate sitemap loc XPath query.');
        }

        $this->assertCount(5, $locNodes);

        $expectedPaths = ['/', '/about', '/imprint', '/terms', '/privacy'];
        foreach ($expectedPaths as $index => $path) {
            $expected = $baseUrl !== ''
                ? rtrim($baseUrl, '/') . $path
                : $path;

            $this->assertSame($expected, (string) $locNodes[$index]);
        }
    }

    private function invokePrivateString(PageController $controller, string $methodName): string
    {
        $method = new ReflectionMethod(PageController::class, $methodName);
        $method->setAccessible(true);
        return (string) $method->invoke($controller);
    }
}
