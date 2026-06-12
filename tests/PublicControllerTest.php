<?php

declare(strict_types=1);

namespace Daybreak\Tests;

use Daybreak\Controller\PublicController;
use ReflectionMethod;

final class PublicControllerTest extends TestCase
{
    public function testNormalizeSourceCategoryAllowsKnownSlug(): void
    {
        $controller = new PublicController();
        $method = new ReflectionMethod(PublicController::class, 'normalizeSourceCategory');
        $method->setAccessible(true);

        $category = $method->invoke($controller, 'privacy', [
            ['slug' => 'critical'],
            ['slug' => 'privacy'],
        ]);

        $this->assertSame('privacy', $category);
    }

    public function testNormalizeSourceCategoryRejectsUnknownSlug(): void
    {
        $controller = new PublicController();
        $method = new ReflectionMethod(PublicController::class, 'normalizeSourceCategory');
        $method->setAccessible(true);

        $category = $method->invoke($controller, 'unknown', [
            ['slug' => 'critical'],
            ['slug' => 'privacy'],
        ]);

        $this->assertNull($category);
    }

    public function testNormalizeSourceCategoryTreatsEmptyInputAsNull(): void
    {
        $controller = new PublicController();
        $method = new ReflectionMethod(PublicController::class, 'normalizeSourceCategory');
        $method->setAccessible(true);

        $category = $method->invoke($controller, '', [
            ['slug' => 'critical'],
        ]);

        $this->assertNull($category);
    }

    public function testNormalizeSourceSearchQueryTrimsAndLimitsLength(): void
    {
        $controller = new PublicController();
        $method = new ReflectionMethod(PublicController::class, 'normalizeSourceSearchQuery');
        $method->setAccessible(true);

        $query = $method->invoke($controller, '  ' . str_repeat('a', 120) . '  ');

        $this->assertSame(100, mb_strlen($query));
        $this->assertSame(str_repeat('a', 100), $query);
    }

    public function testNormalizeSourceSortFallsBackToDefault(): void
    {
        $controller = new PublicController();
        $method = new ReflectionMethod(PublicController::class, 'normalizeSourceSort');
        $method->setAccessible(true);

        $sort = $method->invoke($controller, 'drop table');

        $this->assertSame('total', $sort);
    }

    public function testNormalizeSourceSortAllowsKnownValue(): void
    {
        $controller = new PublicController();
        $method = new ReflectionMethod(PublicController::class, 'normalizeSourceSort');
        $method->setAccessible(true);

        $sort = $method->invoke($controller, 'recent_7d');

        $this->assertSame('recent_7d', $sort);
    }

    public function testBuildDailyBreakdownBuildsWindowTotals(): void
    {
        $controller = new PublicController();
        $method = new ReflectionMethod(PublicController::class, 'buildDailyBreakdown');
        $method->setAccessible(true);

        $sources = [
            ['id' => 1, 'name' => 'Alpha', 'category_name' => 'Privacy'],
        ];
        $rows = [
            ['source_id' => 1, 'day_key' => (new \DateTimeImmutable('today'))->format('Y-m-d'), 'article_count' => 3],
            ['source_id' => 1, 'day_key' => (new \DateTimeImmutable('today'))->sub(new \DateInterval('P1D'))->format('Y-m-d'), 'article_count' => 2],
        ];

        $breakdown = $method->invoke($controller, $sources, $rows, 3, 20);

        $this->assertCount(3, $breakdown['day_keys']);
        $this->assertCount(1, $breakdown['rows']);
        $this->assertSame(5, $breakdown['rows'][0]['window_total']);
    }

    public function testBuildDerivedSourceMetricsCalculatesStreakAndBurstiness(): void
    {
        $controller = new PublicController();
        $method = new ReflectionMethod(PublicController::class, 'buildDerivedSourceMetrics');
        $method->setAccessible(true);

        $today = new \DateTimeImmutable('today');
        $rows = [
            ['source_id' => 1, 'source_name' => 'Alpha', 'category_name' => 'Privacy', 'day_key' => $today->format('Y-m-d'), 'article_count' => 4],
            ['source_id' => 1, 'source_name' => 'Alpha', 'category_name' => 'Privacy', 'day_key' => $today->sub(new \DateInterval('P1D'))->format('Y-m-d'), 'article_count' => 2],
            ['source_id' => 1, 'source_name' => 'Alpha', 'category_name' => 'Privacy', 'day_key' => $today->sub(new \DateInterval('P3D'))->format('Y-m-d'), 'article_count' => 1],
        ];

        $metrics = $method->invoke($controller, $rows, 5);

        $this->assertSame('Alpha', $metrics['consistency_leaders'][0]['source_name']);
        $this->assertSame(2, $metrics['consistency_leaders'][0]['longest_streak_30d']);
        $this->assertSame(4, $metrics['bursty_sources'][0]['max_single_day_30d']);
    }
}
