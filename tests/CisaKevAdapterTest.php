<?php

declare(strict_types=1);

namespace Daybreak\Tests;

use Daybreak\Adapter\CisaKevAdapter;

final class CisaKevAdapterTest extends TestCase
{
    private const URL = 'https://www.cisa.gov/sites/default/files/feeds/known_exploited_vulnerabilities.json';

    private function source(string $etag = null): array
    {
        return ['feed_url' => self::URL, 'etag' => $etag, 'last_modified_hdr' => null];
    }

    private function response(array $vulns, string $etag = 'kev-1'): array
    {
        return [
            'status'        => 200,
            'body'          => json_encode(['vulnerabilities' => $vulns], JSON_THROW_ON_ERROR),
            'etag'          => $etag,
            'last_modified' => null,
            'not_modified'  => false,
        ];
    }

    public function testFetchMapsCveFields(): void
    {
        $fetcher = new FakeFetchClient([self::URL => $this->response([[
            'cveID'                       => 'CVE-2024-1234',
            'vendorProject'               => 'Cisco',
            'product'                     => 'IOS XE',
            'vulnerabilityName'           => 'Command Injection',
            'shortDescription'            => 'Allows unauthenticated RCE.',
            'requiredAction'              => 'Apply updates per vendor instructions.',
            'knownRansomwareCampaignUse'  => 'Unknown',
            'dateAdded'                   => '2024-06-01',
        ]])]);

        $result = (new CisaKevAdapter())->fetch($this->source(), $fetcher);

        $this->assertCount(1, $result->items);
        $item = $result->items[0];
        $this->assertSame('CVE-2024-1234', $item->guid);
        $this->assertStringContains('CVE-2024-1234', $item->title);
        $this->assertStringContains('Cisco', $item->title);
        $this->assertSame('https://nvd.nist.gov/vuln/detail/CVE-2024-1234', $item->url);
        $this->assertStringContains('Allows unauthenticated RCE.', (string) $item->summary);
        $this->assertSame('2024-06-01 00:00:00', $item->publishedAt?->format('Y-m-d H:i:s'));
        $this->assertSame('kev-1', $result->etag);
    }

    public function testFetchPrefixesRansomwareLabel(): void
    {
        $fetcher = new FakeFetchClient([self::URL => $this->response([[
            'cveID'                       => 'CVE-2024-9999',
            'vendorProject'               => 'ACME',
            'product'                     => 'Gadget',
            'vulnerabilityName'           => 'XSS',
            'shortDescription'            => 'Reflected XSS.',
            'requiredAction'              => 'Patch now.',
            'knownRansomwareCampaignUse'  => 'Known',
            'dateAdded'                   => '2024-05-01',
        ]])]);

        $result = (new CisaKevAdapter())->fetch($this->source(), $fetcher);

        $this->assertCount(1, $result->items);
        $this->assertStringContains('[Ransomware]', (string) $result->items[0]->summary);
    }

    public function testFetchSortsDescByDateAdded(): void
    {
        $fetcher = new FakeFetchClient([self::URL => $this->response([
            [
                'cveID' => 'CVE-2023-0001', 'vendorProject' => 'A', 'product' => 'X',
                'vulnerabilityName' => 'Old', 'shortDescription' => 'Old.',
                'requiredAction' => '', 'knownRansomwareCampaignUse' => 'Unknown',
                'dateAdded' => '2023-01-01',
            ],
            [
                'cveID' => 'CVE-2024-9900', 'vendorProject' => 'B', 'product' => 'Y',
                'vulnerabilityName' => 'New', 'shortDescription' => 'New.',
                'requiredAction' => '', 'knownRansomwareCampaignUse' => 'Unknown',
                'dateAdded' => '2024-12-31',
            ],
        ])]);

        $result = (new CisaKevAdapter())->fetch($this->source(), $fetcher);

        $this->assertCount(2, $result->items);
        $this->assertSame('CVE-2024-9900', $result->items[0]->guid, 'Newer entry should be first');
    }

    public function testFetchHonorsNotModified(): void
    {
        $fetcher = new FakeFetchClient([self::URL => [
            'status'        => 304,
            'body'          => '',
            'etag'          => 'kev-old',
            'last_modified' => null,
            'not_modified'  => true,
        ]]);

        $result = (new CisaKevAdapter())->fetch($this->source('kev-old'), $fetcher);

        $this->assertTrue($result->notModified);
        $this->assertCount(0, $result->items);
    }

    public function testFetchSkipsEntryWithEmptyCveId(): void
    {
        $fetcher = new FakeFetchClient([self::URL => $this->response([
            [
                'cveID' => '', 'vendorProject' => 'X', 'product' => 'Y',
                'vulnerabilityName' => 'Test', 'shortDescription' => 'Desc.',
                'requiredAction' => '', 'knownRansomwareCampaignUse' => 'Unknown',
                'dateAdded' => '2024-01-01',
            ],
        ])]);

        $result = (new CisaKevAdapter())->fetch($this->source(), $fetcher);

        $this->assertCount(0, $result->items);
    }
}
