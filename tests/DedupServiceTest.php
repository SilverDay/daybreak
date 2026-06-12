<?php

declare(strict_types=1);

namespace Daybreak\Tests;

use Daybreak\Service\DedupService;

final class DedupServiceTest extends TestCase
{
    public function testGroupMergesSourcesWithSameDedupKey(): void
    {
        $grouped = DedupService::group([
            ['dedup_key' => 'abc', 'source_name' => 'Alpha', 'title' => 'One', 'url' => 'https://example.test/story-1'],
            ['dedup_key' => 'abc', 'source_name' => 'Beta', 'title' => 'One', 'url' => 'https://example.test/story-1?utm=1'],
            ['dedup_key' => 'abc', 'source_name' => 'Beta', 'title' => 'One', 'url' => 'https://example.test/story-1'],
            ['dedup_key' => 'xyz', 'source_name' => 'Gamma', 'title' => 'Two', 'url' => 'https://example.test/story-2'],
        ]);

        $this->assertCount(2, $grouped);
        $this->assertSame(['Beta'], $grouped[0]['also_by']);
        $this->assertSame([], $grouped[1]['also_by']);
    }

    public function testGroupDoesNotMergeDifferentUrlsWithSameDedupKey(): void
    {
        $grouped = DedupService::group([
            ['dedup_key' => 'abc', 'source_name' => 'Alpha', 'title' => 'One', 'url' => 'https://one.example.test/story-a'],
            ['dedup_key' => 'abc', 'source_name' => 'Beta', 'title' => 'One', 'url' => 'https://two.example.test/story-b'],
        ]);

        $this->assertCount(2, $grouped);
        $this->assertSame([], $grouped[0]['also_by']);
        $this->assertSame([], $grouped[1]['also_by']);
    }

    public function testGroupLeavesMissingKeysUntouched(): void
    {
        $grouped = DedupService::group([
            ['dedup_key' => '', 'source_name' => 'Alpha', 'title' => 'One'],
            ['source_name' => 'Beta', 'title' => 'Two'],
        ]);

        $this->assertCount(2, $grouped);
        $this->assertSame([], $grouped[0]['also_by']);
        $this->assertSame([], $grouped[1]['also_by']);
    }
}
