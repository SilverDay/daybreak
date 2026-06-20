<?php

declare(strict_types=1);

namespace Daybreak\Tests;

use Daybreak\Service\EpssService;

/**
 * Tests for EpssService::parseBody() — the pure JSON-parsing helper.
 * DB-dependent methods (findDue, fetchAndStore, refreshDue) are not unit-testable
 * without a live database; they are covered by the manual verification steps.
 */
final class EpssServiceTest extends TestCase
{
    private function callParseBody(string $json): array
    {
        $m = new \ReflectionMethod(EpssService::class, 'parseBody');
        $m->setAccessible(true);
        return $m->invoke(new EpssService(new FakeFetchClient([])), $json);
    }

    public function testParsesValidResponse(): void
    {
        $json = json_encode([
            'status' => 'OK',
            'data'   => [
                ['cve' => 'CVE-2024-1234', 'epss' => '0.93200', 'percentile' => '0.99812', 'date' => '2026-06-19'],
                ['cve' => 'CVE-2023-5678', 'epss' => '0.00048', 'percentile' => '0.12963', 'date' => '2026-06-19'],
            ],
        ], JSON_THROW_ON_ERROR);

        $rows = $this->callParseBody($json);

        $this->assertCount(2, $rows);
        $this->assertSame('CVE-2024-1234', $rows[0]['cve_id']);
        $this->assertSame(0.932, $rows[0]['epss_score']);
        $this->assertSame('CVE-2023-5678', $rows[1]['cve_id']);
    }

    public function testScoreIsCastToFloat(): void
    {
        $json = json_encode(['status' => 'OK', 'data' => [
            ['cve' => 'CVE-2024-0001', 'epss' => '0.12345', 'percentile' => '0.55000'],
        ]], JSON_THROW_ON_ERROR);

        $rows = $this->callParseBody($json);

        $this->assertTrue(is_float($rows[0]['epss_score']), 'epss_score should be float');
        $this->assertTrue(is_float($rows[0]['percentile']), 'percentile should be float');
        $this->assertSame(0.12345, $rows[0]['epss_score']);
        $this->assertSame(0.55, $rows[0]['percentile']);
    }

    public function testSkipsEntryWithMissingCve(): void
    {
        $json = json_encode(['status' => 'OK', 'data' => [
            ['epss' => '0.50000', 'percentile' => '0.80000'],
        ]], JSON_THROW_ON_ERROR);

        $this->assertCount(0, $this->callParseBody($json));
    }

    public function testSkipsEntryWithEmptyCve(): void
    {
        $json = json_encode(['status' => 'OK', 'data' => [
            ['cve' => '', 'epss' => '0.50000', 'percentile' => '0.80000'],
        ]], JSON_THROW_ON_ERROR);

        $this->assertCount(0, $this->callParseBody($json));
    }

    public function testSkipsEntryWithMissingEpss(): void
    {
        $json = json_encode(['status' => 'OK', 'data' => [
            ['cve' => 'CVE-2024-9999', 'percentile' => '0.80000'],
        ]], JSON_THROW_ON_ERROR);

        $this->assertCount(0, $this->callParseBody($json));
    }

    public function testSkipsEntryWithEmptyEpss(): void
    {
        $json = json_encode(['status' => 'OK', 'data' => [
            ['cve' => 'CVE-2024-9999', 'epss' => '', 'percentile' => '0.80000'],
        ]], JSON_THROW_ON_ERROR);

        $this->assertCount(0, $this->callParseBody($json));
    }

    public function testReturnsEmptyArrayForInvalidJson(): void
    {
        $this->assertCount(0, $this->callParseBody('not-json'));
    }

    public function testReturnsEmptyArrayWhenDataKeyAbsent(): void
    {
        $json = json_encode(['status' => 'OK', 'total' => 0], JSON_THROW_ON_ERROR);
        $this->assertCount(0, $this->callParseBody($json));
    }

    public function testMissingPercentileDefaultsToZero(): void
    {
        $json = json_encode(['status' => 'OK', 'data' => [
            ['cve' => 'CVE-2024-0002', 'epss' => '0.10000'],
        ]], JSON_THROW_ON_ERROR);

        $rows = $this->callParseBody($json);

        $this->assertCount(1, $rows);
        $this->assertSame(0.0, $rows[0]['percentile']);
    }
}
