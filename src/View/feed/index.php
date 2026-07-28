<?php

declare(strict_types=1);

use Daybreak\Security\Html;
use Daybreak\Security\Csrf;

/** @var bool $canBookmarkToKioju */
?>
<h1 class="feed-page-title"><?= Html::e($title ?? 'Latest') ?></h1>
<?php if (empty($articles)): ?>
  <div class="no-articles">
    <p>No articles found for the selected filter and time window. Try expanding the time window or selecting a different category.</p>
  </div>
<?php else: ?>
  <?php foreach ($articles as $a): ?>
    <article class="article-card<?= ($a['read'] ?? false) ? ' article-card--read' : '' ?>"
      data-article-id="<?= (int) ($a['id'] ?? 0) ?>"
      <?= !empty($a['source_language']) ? 'lang="' . Html::e((string) $a['source_language']) . '"' : '' ?>>
      <div class="article-meta">
        <span class="source-badge" data-badge-color="<?= Html::e($a['color'] ?? '#909090') ?>">
          <?= Html::e($a['source_name']) ?>
        </span>
        <?php if (in_array($a['source_status'] ?? 'active', ['degraded', 'auto_disabled'], true)): ?>
          <?php $dotState = ($a['source_status'] === 'auto_disabled') ? 'down' : 'degraded'; ?>
          <span class="source-freshness-dot source-freshness-dot--<?= Html::e($dotState) ?>"
            title="Source is <?= Html::e($dotState) ?> — coverage may be incomplete"
            aria-label="Source health: <?= Html::e($dotState) ?>"></span>
        <?php endif; ?>
        <?php if (!empty($a['category'])): ?>
          <span class="article-cat"><?= Html::e($a['category']) ?></span>
        <?php endif; ?>
        <?php if (($canBookmarkToKioju ?? false) === true): ?>
          <form method="post" action="/bookmark" class="article-bookmark-form">
            <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
            <input type="hidden" name="url" value="<?= Html::e($a['url']) ?>">
            <input type="hidden" name="title" value="<?= Html::e($a['title']) ?>">
            <input type="hidden" name="origin" value="public">
            <button type="submit" class="btn btn-secondary btn-sm">Add to Kioju</button>
          </form>
        <?php endif; ?>
        <?php if (!empty($a['published_at'])): ?>
          <time class="article-time" datetime="<?= Html::e($a['published_at']) ?>"
            title="<?= Html::e($a['published_at']) ?>">
            <?= relativeTime($a['published_at']) ?>
          </time>
        <?php endif; ?>
      </div>
      <h3 class="article-title">
        <a href="<?= Html::e($a['url']) ?>" target="_blank" rel="noopener noreferrer nofollow">
          <?= Html::e($a['title']) ?>
        </a>
      </h3>
      <?php if (!empty($a['summary'])): ?>
        <p class="article-summary"><?= Html::e(Html::sanitizeSummary((string) $a['summary'], 220)) ?></p>
      <?php endif; ?>
      <p class="article-attribution">
        <?= Html::e($a['attribution_text']) ?>
        <?php if (!empty($a['also_by'])): ?>
          <span class="article-also-by">· Also: <?= Html::e(implode(', ', $a['also_by'])) ?><?php if (!empty($a['also_by_omitted'])): ?> +<?= (int) $a['also_by_omitted'] ?> more<?php endif; ?></span>
        <?php endif; ?>
      </p>
    </article>
  <?php endforeach; ?>
<?php endif; ?>
<?php if (($totalPages ?? 1) > 1 && isset($paginationBase)): ?>
  <nav class="feed-pagination" aria-label="Page navigation">
    <?php if (($page ?? 1) > 1): ?>
      <a href="<?= Html::e($paginationBase . 1) ?>" class="btn btn-secondary btn-sm">First</a>
      <a href="<?= Html::e($paginationBase . (($page ?? 1) - 1)) ?>" class="btn btn-secondary btn-sm">&larr; Prev</a>
    <?php endif; ?>
    <span class="feed-pagination-info">Page <?= (int) ($page ?? 1) ?> of <?= (int) ($totalPages ?? 1) ?></span>
    <?php if (($page ?? 1) < ($totalPages ?? 1)): ?>
      <a href="<?= Html::e($paginationBase . (($page ?? 1) + 1)) ?>" class="btn btn-secondary btn-sm">Next &rarr;</a>
      <a href="<?= Html::e($paginationBase . ($totalPages ?? 1)) ?>" class="btn btn-secondary btn-sm">Last</a>
    <?php endif; ?>
  </nav>
<?php endif; ?>
