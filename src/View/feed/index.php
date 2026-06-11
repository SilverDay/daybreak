<?php
declare(strict_types=1);

use Daybreak\Security\Html;
?>
<?php if (empty($articles)): ?>
<div class="no-articles">
  <p>No articles found for the selected filter and time window. Try expanding the time window or selecting a different category.</p>
</div>
<?php else: ?>
<?php foreach ($articles as $a): ?>
<article class="article-card">
  <div class="article-meta">
    <span class="source-badge" style="--badge-color:<?= Html::e($a['color'] ?? '#909090') ?>">
      <?= Html::e($a['source_name']) ?>
    </span>
    <?php if (!empty($a['category'])): ?>
    <span class="article-cat"><?= Html::e($a['category']) ?></span>
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
  <p class="article-summary"><?= Html::e($a['summary']) ?></p>
  <?php endif; ?>
  <p class="article-attribution">
    <?= Html::e($a['attribution_text']) ?>
    <?php if (!empty($a['also_by'])): ?>
    <span class="article-also-by">· Also: <?= Html::e(implode(', ', $a['also_by'])) ?></span>
    <?php endif; ?>
  </p>
</article>
<?php endforeach; ?>
<?php endif; ?>
