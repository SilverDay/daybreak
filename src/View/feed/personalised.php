<?php
declare(strict_types=1);

use Daybreak\Security\Html;

// $sinceMode    — bool: user requested since-last-visit mode
// $sinceQuery   — bool: we actually queried by timestamp (false on first visit)
// $lastSeen     — string|null: the previous last_seen_at value (already used; DB now updated)
// $unreadCount  — int|null: count of new items (only when $sinceQuery)
// $windowDays   — int: days fallback (used when no previous visit)

if ($sinceMode): ?>
<div class="since-banner<?= ($sinceQuery && $unreadCount !== null) ? '' : ' since-banner--init' ?>">
  <?php if ($sinceQuery && $unreadCount !== null): ?>
    <strong class="since-count"><?= $unreadCount ?></strong>
    new <?= $unreadCount === 1 ? 'item' : 'items' ?> since
    <time datetime="<?= Html::e($lastSeen ?? '') ?>">
      <?= Html::e(date('M j, g:i a', strtotime($lastSeen ?? 'now'))) ?>
    </time>
  <?php else: ?>
    First visit &mdash; showing last <?= $windowDays ?> day<?= $windowDays !== 1 ? 's' : '' ?>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php if (empty($articles)): ?>
<div class="no-articles">
  <p>Nothing new since your last visit. Check back later, or switch to a longer time window.</p>
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
  <p class="article-attribution"><?= Html::e($a['attribution_text']) ?></p>
</article>
<?php endforeach; ?>
<?php endif; ?>
