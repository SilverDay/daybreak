<?php

declare(strict_types=1);

use Daybreak\Security\Html;

$starred    = $starred    ?? [];
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;

?>
<?php if (empty($starred)): ?>
  <div class="no-articles">
    <p>No starred articles yet. Click the star on any article in My Feed to save it here.</p>
  </div>
<?php else: ?>
  <?php foreach ($starred as $a): ?>
    <article class="article-card">
      <div class="article-meta">
        <span class="source-badge" data-badge-color="#909090">
          <?= Html::e($a['source_name']) ?>
        </span>
        <?php if ($a['detached']): ?>
          <span class="detached-badge">no longer in feed</span>
        <?php endif; ?>
        <button type="button"
            class="star-btn star-btn--active"
            data-article-id="<?= (int) $a['article_id'] ?>"
            aria-label="Unstar article">
          <svg class="star-icon" width="14" height="14" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
          </svg>
        </button>
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
    </article>
  <?php endforeach; ?>
<?php endif; ?>
<?php if ($totalPages > 1): ?>
  <nav class="feed-pagination" aria-label="Page navigation">
    <?php if ($page > 1): ?>
      <a href="<?= Html::e('/starred?page=1') ?>" class="btn btn-secondary btn-sm">First</a>
      <a href="<?= Html::e('/starred?page=' . ($page - 1)) ?>" class="btn btn-secondary btn-sm">&larr; Prev</a>
    <?php endif; ?>
    <span class="feed-pagination-info">Page <?= $page ?> of <?= $totalPages ?></span>
    <?php if ($page < $totalPages): ?>
      <a href="<?= Html::e('/starred?page=' . ($page + 1)) ?>" class="btn btn-secondary btn-sm">Next &rarr;</a>
      <a href="<?= Html::e('/starred?page=' . $totalPages) ?>" class="btn btn-secondary btn-sm">Last</a>
    <?php endif; ?>
  </nav>
<?php endif; ?>
