<?php

declare(strict_types=1);

namespace Daybreak\Tests;

use Daybreak\Adapter\GitHubAdvisoryAdapter;

final class GitHubAdvisoryAdapterTest extends TestCase
{
    private const BASE_URL = 'https://api.github.com/advisories';
    private const FETCH_URL = self::BASE_URL . '?type=reviewed&per_page=30&sort=published&direction=desc';

    private function source(string $etag = null): array
    {
        return ['feed_url' => self::BASE_URL, 'etag' => $etag, 'last_modified_hdr' => null];
    }

    private function advisory(array $overrides = []): array
    {
        return array_merge([
            'ghsa_id'      => 'GHSA-abcd-1234-efgh',
            'cve_id'       => 'CVE-2024-5678',
            'summary'      => 'Remote code execution in foobar',
            'description'  => 'An attacker can execute arbitrary code.',
            'severity'     => 'high',
            'published_at' => '2024-06-01T12:00:00Z',
            'html_url'     => 'https://github.com/advisories/GHSA-abcd-1234-efgh',
            'cvss'         => ['score' => 9.8, 'vector_string' => 'CVSS:3.1/AV:N/AC:L'],
            'vulnerabilities' => [
                ['package' => ['ecosystem' => 'npm', 'name' => 'foobar'], 'vulnerable_version_range' => '<2.0.0'],
            ],
        ], $overrides);
    }

    private function okResponse(array $advisories, string $etag = 'gh-1'): array
    {
        return [
            'status'        => 200,
            'body'          => json_encode($advisories, JSON_THROW_ON_ERROR),
            'etag'          => $etag,
            'last_modified' => null,
            'not_modified'  => false,
        ];
    }

    public function testFetchMapsGhsaIdAsGuid(): void
    {
        $fetcher = new FakeFetchClient([self::FETCH_URL => $this->okResponse([$this->advisory()])]);
        $result  = (new GitHubAdvisoryAdapter())->fetch($this->source(), $fetcher);

        $this->assertCount(1, $result->items);
        $this->assertSame('GHSA-abcd-1234-efgh', $result->items[0]->guid);
        $this->assertSame('https://github.com/advisories/GHSA-abcd-1234-efgh', $result->items[0]->url);
    }

    public function testFetchTitleIsSeverityColonSummary(): void
    {
        $fetcher = new FakeFetchClient([self::FETCH_URL => $this->okResponse([$this->advisory()])]);
        $result  = (new GitHubAdvisoryAdapter())->fetch($this->source(), $fetcher);

        $this->assertSame('HIGH: Remote code execution in foobar', $result->items[0]->title);
    }

    public function testFetchSummaryContainsCvssAndPackage(): void
    {
        $fetcher = new FakeFetchClient([self::FETCH_URL => $this->okResponse([$this->advisory()])]);
        $result  = (new GitHubAdvisoryAdapter())->fetch($this->source(), $fetcher);

        $summary = (string) $result->items[0]->summary;
        $this->assertStringContains('CVE-2024-5678', $summary);
        $this->assertStringContains('9.8', $summary);
        $this->assertStringContains('npm:foobar', $summary);
    }

    public function testFetchStripsMarkdownHeadingsFromDescription(): void
    {
        $fetcher = new FakeFetchClient([self::FETCH_URL => $this->okResponse([$this->advisory([
            'description' => "## Summary\nAn attacker can exploit this.\n\n## Details\nMore info here.",
        ])])]);
        $result = (new GitHubAdvisoryAdapter())->fetch($this->source(), $fetcher);

        $summary = (string) $result->items[0]->summary;
        $this->assertTrue(!str_contains($summary, '## Summary'), 'Markdown heading should be stripped');
        $this->assertStringContains('An attacker can exploit this.', $summary);
    }

    public function testFetchParsesPublishedAt(): void
    {
        $fetcher = new FakeFetchClient([self::FETCH_URL => $this->okResponse([$this->advisory()])]);
        $result  = (new GitHubAdvisoryAdapter())->fetch($this->source(), $fetcher);

        $this->assertSame('2024-06-01 12:00:00', $result->items[0]->publishedAt?->format('Y-m-d H:i:s'));
    }

    public function testFetchHonorsNotModified(): void
    {
        $fetcher = new FakeFetchClient([self::FETCH_URL => [
            'status'        => 304,
            'body'          => '',
            'etag'          => 'gh-old',
            'last_modified' => null,
            'not_modified'  => true,
        ]]);

        $result = (new GitHubAdvisoryAdapter())->fetch($this->source('gh-old'), $fetcher);

        $this->assertTrue($result->notModified);
        $this->assertCount(0, $result->items);
    }

    public function testFetchSkipsItemsWithMissingHtmlUrl(): void
    {
        $fetcher = new FakeFetchClient([self::FETCH_URL => $this->okResponse([
            $this->advisory(['html_url' => '']),
        ])]);
        $result = (new GitHubAdvisoryAdapter())->fetch($this->source(), $fetcher);

        $this->assertCount(0, $result->items);
    }

    public function testFetchHandlesUnknownSeverity(): void
    {
        $fetcher = new FakeFetchClient([self::FETCH_URL => $this->okResponse([
            $this->advisory(['severity' => 'unknown', 'cvss' => null]),
        ])]);
        $result = (new GitHubAdvisoryAdapter())->fetch($this->source(), $fetcher);

        $this->assertCount(1, $result->items);
        $this->assertStringContains('UNKNOWN:', $result->items[0]->title);
    }

    public function testFetchEtagPassedThrough(): void
    {
        $fetcher = new FakeFetchClient([self::FETCH_URL => $this->okResponse([], 'gh-new')]);
        $result  = (new GitHubAdvisoryAdapter())->fetch($this->source(), $fetcher);

        $this->assertSame('gh-new', $result->etag);
    }
}
