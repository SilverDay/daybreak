<?php

declare(strict_types=1);

use Daybreak\Security\Html;

$summary = isset($summary) && is_array($summary) ? $summary : [];
$sources = isset($sources) && is_array($sources) ? $sources : [];
$categories = isset($categories) && is_array($categories) ? $categories : [];
$activeCategory = isset($activeCategory) ? $activeCategory : null;
$searchQuery = isset($searchQuery) && is_string($searchQuery) ? $searchQuery : '';
$sortKey = isset($sortKey) && is_string($sortKey) ? $sortKey : 'total';
$mostActive7d = isset($mostActive7d) && is_array($mostActive7d) ? $mostActive7d : [];
$mostActive30d = isset($mostActive30d) && is_array($mostActive30d) ? $mostActive30d : [];
$freshnessLeaders = isset($freshnessLeaders) && is_array($freshnessLeaders) ? $freshnessLeaders : [];
$activeTodayCount = (int) (($activeToday['source_count'] ?? 0));
$staleSourceCount = (int) (($staleSources['source_count'] ?? 0));
$dailyBreakdown = isset($dailyBreakdown) && is_array($dailyBreakdown) ? $dailyBreakdown : ['day_keys' => [], 'rows' => []];
$derivedMetrics = isset($derivedMetrics) && is_array($derivedMetrics) ? $derivedMetrics : [];
$dailyBreakdownRows = is_array($dailyBreakdown['rows'] ?? null) ? $dailyBreakdown['rows'] : [];
$dailyBreakdownDays = is_array($dailyBreakdown['day_keys'] ?? null) ? $dailyBreakdown['day_keys'] : [];
$consistencyLeaders = is_array($derivedMetrics['consistency_leaders'] ?? null) ? $derivedMetrics['consistency_leaders'] : [];
$quietReliable = is_array($derivedMetrics['quiet_reliable'] ?? null) ? $derivedMetrics['quiet_reliable'] : [];
$burstySources = is_array($derivedMetrics['bursty_sources'] ?? null) ? $derivedMetrics['bursty_sources'] : [];
?>
<div class="search-page sources-page public-data-page">
    <div class="search-card public-panel public-panel--hero">
        <h1 class="search-title">Sources</h1>
        <p class="suggest-desc sources-intro">Browse the public source base behind Daybreak and see how much each source contributes to the archive.</p>

        <div class="admin-stat-grid sources-summary-grid">
            <div class="admin-stat-card public-stat-card">
                <div class="admin-stat-value"><?= (int) ($summary['total_sources'] ?? 0) ?></div>
                <div class="admin-stat-label">Public sources</div>
            </div>
            <div class="admin-stat-card public-stat-card">
                <div class="admin-stat-value"><?= (int) ($summary['total_articles'] ?? 0) ?></div>
                <div class="admin-stat-label">Stored articles</div>
            </div>
            <div class="admin-stat-card public-stat-card">
                <div class="admin-stat-value"><?= (int) ($summary['articles_24h'] ?? 0) ?></div>
                <div class="admin-stat-label">Articles in last 24h</div>
            </div>
            <div class="admin-stat-card public-stat-card">
                <div class="admin-stat-value"><?= (int) ($summary['active_sources_7d'] ?? 0) ?></div>
                <div class="admin-stat-label">Active sources in last 7d</div>
            </div>
        </div>

        <form method="get" action="/sources" class="search-form">
            <div class="search-input-group">
                <input type="text" name="q" class="search-input"
                    placeholder="Search source names…"
                    value="<?= Html::e($searchQuery) ?>"
                    autocomplete="off" maxlength="100">
                <button type="submit" class="btn btn-primary">Apply</button>
            </div>

            <div class="search-filters">
                <div class="filter-item">
                    <label for="sources-category" class="filter-label">Category</label>
                    <select id="sources-category" name="category" class="filter-select">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= Html::e((string) $cat['slug']) ?>" <?= $activeCategory === $cat['slug'] ? ' selected' : '' ?>>
                                <?= Html::e((string) $cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <?php if ($activeCategory !== null || $searchQuery !== ''): ?>
                    <a href="/sources" class="btn btn-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-wrap sources-table-wrap public-data-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th data-sort="text" data-sort-dir="asc">Source</th>
                    <th data-sort="text">Category</th>
                    <th data-sort="text">Health</th>
                    <th class="num" data-sort="num">24h</th>
                    <th class="num" data-sort="num">7d</th>
                    <th class="num" data-sort="num">30d</th>
                    <th class="num" data-sort="num">Articles</th>
                    <th data-sort="date">Latest article</th>
                    <th>Homepage</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sources as $source): ?>
                    <?php
                        $freshness = sourceFreshness(
                            (string) ($source['status'] ?? 'active'),
                            isset($source['latest_article_at']) ? (string) $source['latest_article_at'] : null,
                            isset($source['last_recovered_at']) ? (string) $source['last_recovered_at'] : null
                        );
                        $rowClass = match($freshness) {
                            'degraded'  => 'health-risk--warn',
                            'down'      => 'health-risk--critical',
                            default     => '',
                        };
                    ?>
                    <tr<?= $rowClass !== '' ? ' class="' . Html::e($rowClass) . '"' : '' ?>>
                        <td>
                            <strong><?= Html::e((string) $source['name']) ?></strong>
                            <?php if (!empty($source['last_success_at'])): ?>
                                <div class="text-secondary text-sm">Fetched <?= Html::e(relativeTime((string) $source['last_success_at'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= Html::e((string) ($source['category_name'] ?? '—')) ?></td>
                        <td>
                            <span class="freshness-badge freshness-badge--<?= Html::e($freshness) ?>">
                                <?= Html::e($freshness) ?>
                            </span>
                        </td>
                        <td class="num"><?= (int) ($source['articles_24h'] ?? 0) ?></td>
                        <td class="num"><?= (int) ($source['articles_7d'] ?? 0) ?></td>
                        <td class="num"><?= (int) ($source['articles_30d'] ?? 0) ?></td>
                        <td class="num"><?= (int) ($source['total_articles'] ?? 0) ?></td>
                        <td data-sort-value="<?= Html::e((string) ($source['latest_article_at'] ?? '')) ?>">
                            <?php if (!empty($source['latest_article_at'])): ?>
                                <strong><?= Html::e(relativeTime((string) $source['latest_article_at'])) ?></strong>
                                <div class="text-secondary text-sm"><?= Html::e((new DateTimeImmutable((string) $source['latest_article_at']))->format('M j, Y H:i')) ?></div>
                            <?php else: ?>
                                <span class="text-secondary">No articles yet</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= Html::e((string) $source['homepage_url']) ?>" target="_blank" rel="noopener noreferrer nofollow" class="text-link">
                                Visit source
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($sources)): ?>
                    <tr>
                        <td colspan="9" class="text-secondary text-center">No public sources matched this filter.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="admin-health-summary sources-snapshot">
        <div class="admin-health-summary-item">
            <span class="admin-health-summary-label">Sources active today</span>
            <strong class="admin-health-summary-value"><?= $activeTodayCount ?></strong>
        </div>
        <div class="admin-health-summary-item">
            <span class="admin-health-summary-label">Sources stale over 7 days</span>
            <strong class="admin-health-summary-value"><?= $staleSourceCount ?></strong>
        </div>
    </div>

    <div class="search-results search-results--grid sources-analytics-grid public-data-grid">
        <div class="search-card public-panel">
            <h2 class="search-title search-title--sub">Most Active Sources</h2>
            <div class="table-wrap">
                <table class="admin-table admin-table--sm">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Category</th>
                            <th class="num">7d</th>
                            <th class="num">30d</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $activeRows = max(count($mostActive7d), count($mostActive30d)); ?>
                        <?php for ($index = 0; $index < $activeRows; $index++): ?>
                            <?php $row7 = $mostActive7d[$index] ?? null; ?>
                            <?php $row30 = $mostActive30d[$index] ?? null; ?>
                            <tr>
                                <td><strong><?= Html::e((string) (($row7['source_name'] ?? $row30['source_name'] ?? '—'))) ?></strong></td>
                                <td><?= Html::e((string) (($row7['category_name'] ?? $row30['category_name'] ?? '—'))) ?></td>
                                <td class="num"><?= (int) ($row7['article_count'] ?? 0) ?></td>
                                <td class="num"><?= (int) ($row30['article_count'] ?? 0) ?></td>
                            </tr>
                        <?php endfor; ?>
                        <?php if ($activeRows === 0): ?>
                            <tr>
                                <td colspan="4" class="text-secondary text-center">No recent activity yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="search-card public-panel">
            <h2 class="search-title search-title--sub">Freshness Leaders</h2>
            <div class="table-wrap">
                <table class="admin-table admin-table--sm">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Category</th>
                            <th>Latest article</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($freshnessLeaders as $source): ?>
                            <tr>
                                <td><strong><?= Html::e((string) ($source['source_name'] ?? '')) ?></strong></td>
                                <td><?= Html::e((string) ($source['category_name'] ?? '—')) ?></td>
                                <td>
                                    <strong><?= Html::e(relativeTime((string) ($source['latest_article_at'] ?? ''))) ?></strong>
                                    <div class="text-secondary text-sm"><?= Html::e((new DateTimeImmutable((string) $source['latest_article_at']))->format('M j, Y H:i')) ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($freshnessLeaders)): ?>
                            <tr>
                                <td colspan="3" class="text-secondary text-center">No source freshness data yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="search-card sources-daily-card public-panel">
        <h2 class="search-title search-title--sub">Articles Per Day Per Source</h2>
        <p class="text-secondary text-sm">Recent 14-day activity for the first 20 visible sources in the current result set.</p>
        <div class="table-wrap">
            <table class="admin-table admin-table--sm">
                <thead>
                    <tr>
                        <th>Source</th>
                        <?php foreach ($dailyBreakdownDays as $dayKey): ?>
                            <th class="num"><?= Html::e((new DateTimeImmutable($dayKey))->format('M j')) ?></th>
                        <?php endforeach; ?>
                        <th class="num">14d</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dailyBreakdownRows as $row): ?>
                        <tr>
                            <td><strong><?= Html::e((string) ($row['source_name'] ?? '')) ?></strong></td>
                            <?php foreach (($row['cells'] ?? []) as $cell): ?>
                                <td class="num<?= ((int) ($cell['count'] ?? 0)) > 0 ? ' sources-day-cell--active' : '' ?>"><?= (int) ($cell['count'] ?? 0) ?></td>
                            <?php endforeach; ?>
                            <td class="num"><strong><?= (int) ($row['window_total'] ?? 0) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($dailyBreakdownRows)): ?>
                        <tr>
                            <td colspan="<?= count($dailyBreakdownDays) + 2 ?>" class="text-secondary text-center">No daily activity data yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="search-results search-results--grid sources-analytics-grid public-data-grid">
        <div class="search-card public-panel">
            <h2 class="search-title search-title--sub">Consistency Leaders</h2>
            <div class="table-wrap">
                <table class="admin-table admin-table--sm">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th class="num">Active days</th>
                            <th class="num">Avg/day</th>
                            <th class="num">Streak</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($consistencyLeaders as $metric): ?>
                            <tr>
                                <td><strong><?= Html::e((string) ($metric['source_name'] ?? '')) ?></strong></td>
                                <td class="num"><?= (int) ($metric['active_days_30d'] ?? 0) ?></td>
                                <td class="num"><?= Html::e(number_format((float) ($metric['avg_articles_per_active_day'] ?? 0), 1)) ?></td>
                                <td class="num"><?= (int) ($metric['longest_streak_30d'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($consistencyLeaders)): ?>
                            <tr>
                                <td colspan="4" class="text-secondary text-center">No consistency data yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="search-card public-panel">
            <h2 class="search-title search-title--sub">Quiet But Reliable</h2>
            <div class="table-wrap">
                <table class="admin-table admin-table--sm">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th class="num">Active days</th>
                            <th class="num">30d total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quietReliable as $metric): ?>
                            <tr>
                                <td><strong><?= Html::e((string) ($metric['source_name'] ?? '')) ?></strong></td>
                                <td class="num"><?= (int) ($metric['active_days_30d'] ?? 0) ?></td>
                                <td class="num"><?= (int) ($metric['total_articles_30d'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($quietReliable)): ?>
                            <tr>
                                <td colspan="3" class="text-secondary text-center">No quiet-and-reliable sources yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="search-card public-panel">
            <h2 class="search-title search-title--sub">Burstiest Sources</h2>
            <div class="table-wrap">
                <table class="admin-table admin-table--sm">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th class="num">Peak day</th>
                            <th class="num">30d total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($burstySources as $metric): ?>
                            <tr>
                                <td><strong><?= Html::e((string) ($metric['source_name'] ?? '')) ?></strong></td>
                                <td class="num"><?= (int) ($metric['max_single_day_30d'] ?? 0) ?></td>
                                <td class="num"><?= (int) ($metric['total_articles_30d'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($burstySources)): ?>
                            <tr>
                                <td colspan="3" class="text-secondary text-center">No burstiness data yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
