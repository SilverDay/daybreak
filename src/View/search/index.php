<?php

declare(strict_types=1);

use Daybreak\Security\Html;
use Daybreak\Security\Csrf;
?>
<?php
// Variables passed from SearchController::search()
/** @var string $q */
/** @var int $windowDays */
/** @var string|null $categorySlug */
/** @var array $categories */
/** @var array $articles */
/** @var bool $searched */
/** @var string $message */
/** @var bool $canBookmarkToKioju */
?>
<div class="search-page">
    <div class="search-card">
        <h1 class="search-title">Search articles</h1>

        <form method="get" action="/search" class="search-form">
            <div class="search-input-group">
                <input type="text" name="q" class="search-input"
                    placeholder="Search headlines and summaries…"
                    value="<?= Html::e($q ?? '') ?>"
                    autocomplete="off" maxlength="500">
                <button type="submit" class="btn btn-primary">Search</button>
            </div>

            <div class="search-filters">
                <div class="filter-item">
                    <label for="search-days" class="filter-label">Time window</label>
                    <select id="search-days" name="days" class="filter-select">
                        <option value="1" <?= ((int) ($windowDays ?? 30)) === 1 ? ' selected' : '' ?>>Last 24h</option>
                        <option value="7" <?= ((int) ($windowDays ?? 30)) === 7 ? ' selected' : '' ?>>Last 7 days</option>
                        <option value="30" <?= ((int) ($windowDays ?? 30)) === 30 ? ' selected' : '' ?>>Last 30 days</option>
                        <option value="60" <?= ((int) ($windowDays ?? 30)) === 60 ? ' selected' : '' ?>>Last 60 days</option>
                        <option value="90" <?= ((int) ($windowDays ?? 30)) === 90 ? ' selected' : '' ?>>Last 90 days</option>
                    </select>
                </div>

                <div class="filter-item">
                    <label for="search-category" class="filter-label">Category</label>
                    <select id="search-category" name="category" class="filter-select">
                        <option value="">All categories</option>
                        <?php foreach ($categories ?? [] as $cat): ?>
                            <option value="<?= Html::e($cat['slug']) ?>" <?= ($activeCategory ?? null) === $cat['slug'] ? ' selected' : '' ?>>
                                <?= Html::e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>

        <?php if ($message): ?>
            <div class="search-message">
                <?= Html::e($message) ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($searched && count($articles ?? []) > 0): ?>
        <div class="search-results">
            <ul class="article-list">
                <?php foreach ($articles as $article): ?>
                    <li class="article-item">
                        <div class="article-header">
                            <h3 class="article-title">
                                <a href="<?= Html::e($article['url']) ?>"
                                    target="_blank" rel="noopener noreferrer nofollow"
                                    class="article-link">
                                    <?= Html::e($article['title']) ?>
                                </a>
                            </h3>
                            <span class="article-source"><?= Html::e($article['source_name']) ?></span>
                            <?php if ($article['category']): ?>
                                <span class="article-category" style="background-color: <?= Html::e($article['color'] ?? '#ccc') ?>">
                                    <?= Html::e($article['category']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($article['summary']): ?>
                            <div class="article-summary">
                                <?= Html::e(mb_substr($article['summary'], 0, 300) . (mb_strlen($article['summary']) > 300 ? '…' : '')) ?>
                            </div>
                        <?php endif; ?>
                        <div class="article-meta">
                            <time class="article-time" datetime="<?= Html::e($article['published_at']) ?>">
                                <?= Html::e(relativeTime($article['published_at'])) ?>
                            </time>
                        </div>
                        <?php if (($canBookmarkToKioju ?? false) === true): ?>
                            <div class="article-actions">
                                <form method="post" action="/bookmark" class="article-bookmark-form">
                                    <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
                                    <input type="hidden" name="url" value="<?= Html::e($article['url']) ?>">
                                    <input type="hidden" name="title" value="<?= Html::e($article['title']) ?>">
                                    <input type="hidden" name="origin" value="search">
                                    <button type="submit" class="btn btn-secondary btn-sm">Save to Kioju</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
