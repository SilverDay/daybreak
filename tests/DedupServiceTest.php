<?php

declare(strict_types=1);

namespace Daybreak\Tests;

use Daybreak\Service\DedupService;

final class DedupServiceTest extends TestCase
{
    public function testResolveGroupsSplitsPrimaryFromDuplicates(): void
    {
        $result = DedupService::resolveGroups([
            ['id' => 3, 'dedup_key' => 'abc', 'source_name' => 'Alpha'],
            ['id' => 2, 'dedup_key' => 'abc', 'source_name' => 'Beta'],
            ['id' => 1, 'dedup_key' => 'abc', 'source_name' => 'Gamma'],
            ['id' => 9, 'dedup_key' => 'xyz', 'source_name' => 'Delta'],
            ['id' => 8, 'dedup_key' => 'xyz', 'source_name' => 'Epsilon'],
        ]);

        $this->assertSame([2, 1, 8], $result['excludeIds']);
        $this->assertCount(2, $result['byKey']);
        $this->assertSame(3, $result['byKey']['abc'][0]['id']);
        $this->assertSame(9, $result['byKey']['xyz'][0]['id']);
    }

    public function testResolveGroupsHandlesEmptyInput(): void
    {
        $result = DedupService::resolveGroups([]);

        $this->assertSame([], $result['excludeIds']);
        $this->assertSame([], $result['byKey']);
    }

    public function testAttachAlsoByLeavesUnmatchedPrimaryEmpty(): void
    {
        $primaries = [
            ['id' => 1, 'dedup_key' => 'abc', 'title' => 'Solo story'],
        ];

        $result = DedupService::attachAlsoBy($primaries, []);

        $this->assertSame([], $result[0]['also_by']);
        $this->assertSame(0, $result[0]['also_by_omitted']);
    }

    public function testAttachAlsoByExcludesThePrimarysOwnRow(): void
    {
        $primaries = [
            ['id' => 3, 'dedup_key' => 'abc', 'title' => 'Same story'],
        ];
        $byKey = [
            'abc' => [
                ['id' => 3, 'dedup_key' => 'abc', 'source_name' => 'Alpha'],
                ['id' => 2, 'dedup_key' => 'abc', 'source_name' => 'Beta'],
                ['id' => 1, 'dedup_key' => 'abc', 'source_name' => 'Gamma'],
            ],
        ];

        $result = DedupService::attachAlsoBy($primaries, $byKey);

        $this->assertSame(['Beta', 'Gamma'], $result[0]['also_by']);
        $this->assertSame(0, $result[0]['also_by_omitted']);
    }

    public function testAttachAlsoByCapsAtMaxAndCountsOmitted(): void
    {
        $primaries = [
            ['id' => 10, 'dedup_key' => 'abc', 'title' => 'Widely covered story'],
        ];
        $members = [['id' => 10, 'dedup_key' => 'abc', 'source_name' => 'Primary']];
        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $i => $name) {
            $members[] = ['id' => 20 + $i, 'dedup_key' => 'abc', 'source_name' => $name];
        }
        $byKey = ['abc' => $members];

        $result = DedupService::attachAlsoBy($primaries, $byKey);

        $this->assertCount(5, $result[0]['also_by']);
        $this->assertSame(['A', 'B', 'C', 'D', 'E'], $result[0]['also_by']);
        $this->assertSame(2, $result[0]['also_by_omitted']);
    }

    public function testAttachAlsoByDoesNotDuplicateRepeatedSourceNames(): void
    {
        $primaries = [
            ['id' => 1, 'dedup_key' => 'abc', 'title' => 'Story'],
        ];
        $byKey = [
            'abc' => [
                ['id' => 1, 'dedup_key' => 'abc', 'source_name' => 'Alpha'],
                ['id' => 2, 'dedup_key' => 'abc', 'source_name' => 'Beta'],
                ['id' => 3, 'dedup_key' => 'abc', 'source_name' => 'Beta'],
            ],
        ];

        $result = DedupService::attachAlsoBy($primaries, $byKey);

        $this->assertSame(['Beta'], $result[0]['also_by']);
        $this->assertSame(0, $result[0]['also_by_omitted']);
    }
}
