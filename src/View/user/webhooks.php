<?php

declare(strict_types=1);

use Daybreak\Security\Html;
use Daybreak\Security\Csrf;

/** @var array $webhooks    rows from user_webhooks */
/** @var array $categories  rows of {slug, name} */
/** @var array $recentLog   recent webhook_log rows */
$webhooks   = $webhooks   ?? [];
$categories = $categories ?? [];
$recentLog  = $recentLog  ?? [];

$formatLabels = ['slack' => 'Slack', 'discord' => 'Discord', 'generic' => 'Generic JSON'];
?>
<div class="settings-page">

  <section class="settings-section">
    <h2 class="settings-section-title">Webhooks</h2>
    <p class="form-hint" style="margin-bottom:1rem">
      Push new articles to Slack, Discord, or any HTTP endpoint on every cron tick.
      Filters are optional &mdash; leave both blank to receive all new articles.
      When both terms <em>and</em> categories are set, the article must match both.
    </p>

    <?php if ($webhooks !== []): ?>
      <ul class="watch-term-list" style="margin-bottom:1.5rem">
        <?php foreach ($webhooks as $wh):
          $filter  = json_decode($wh['filter_json'] ?? '{}', true) ?? [];
          $terms   = implode(', ', (array) ($filter['terms']      ?? []));
          $cats    = implode(', ', (array) ($filter['categories'] ?? []));
          $active  = (bool) $wh['active'];
          $fmtLabel = $formatLabels[$wh['format']] ?? Html::e($wh['format']);
        ?>
          <li class="watch-term-item" style="flex-direction:column;align-items:flex-start;gap:0.35rem;padding:0.75rem 0;border-bottom:1px solid var(--color-border)">
            <div style="display:flex;align-items:center;gap:0.6rem;width:100%">
              <strong style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= Html::e($wh['name']) ?></strong>
              <span class="source-badge" style="--badge-color:<?= $wh['format'] === 'slack' ? '#4a154b' : ($wh['format'] === 'discord' ? '#5865f2' : '#334155') ?>"><?= Html::e($fmtLabel) ?></span>
              <?php if (!$active): ?><span class="source-badge" style="--badge-color:#94a3b8">Paused</span><?php endif; ?>
              <form method="post" action="/settings/webhooks/<?= (int) $wh['id'] ?>">
                <input type="hidden" name="_csrf"   value="<?= Html::e(Csrf::token()) ?>">
                <input type="hidden" name="action"  value="toggle">
                <button type="submit" class="btn btn-secondary btn-sm"><?= $active ? 'Pause' : 'Resume' ?></button>
              </form>
              <form method="post" action="/settings/webhooks/<?= (int) $wh['id'] ?>">
                <input type="hidden" name="_csrf"   value="<?= Html::e(Csrf::token()) ?>">
                <input type="hidden" name="action"  value="delete">
                <button type="submit" class="btn btn-secondary btn-sm">Delete</button>
              </form>
            </div>
            <div class="form-hint" style="font-size:0.78rem;word-break:break-all"><?= Html::e(mb_substr($wh['url'], 0, 80)) ?><?= mb_strlen($wh['url']) > 80 ? '…' : '' ?></div>
            <?php if ($terms !== '' || $cats !== ''): ?>
              <div class="form-hint" style="font-size:0.78rem">
                <?php if ($terms !== ''): ?>Terms: <strong><?= Html::e($terms) ?></strong><?php endif; ?>
                <?php if ($terms !== '' && $cats !== ''): ?> &middot; <?php endif; ?>
                <?php if ($cats !== ''): ?>Categories: <strong><?= Html::e($cats) ?></strong><?php endif; ?>
              </div>
            <?php else: ?>
              <div class="form-hint" style="font-size:0.78rem">No filter &mdash; receives all new articles</div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="form-hint" style="margin-bottom:1rem">No webhooks configured.</p>
    <?php endif; ?>

    <?php if (count($webhooks) < 10): ?>
      <form method="post" action="/settings/webhooks">
        <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">

        <div class="form-group">
          <label class="form-label" for="wh_name">Name</label>
          <input id="wh_name" class="form-input" type="text" name="name"
            maxlength="120" required autocomplete="off" placeholder="e.g. Security Slack">
        </div>

        <div class="form-group">
          <label class="form-label" for="wh_url">Webhook URL</label>
          <input id="wh_url" class="form-input" type="url" name="url"
            required autocomplete="off" placeholder="https://hooks.slack.com/services/…">
          <p class="form-hint">Slack / Discord incoming webhook URL, or any HTTPS endpoint.</p>
        </div>

        <div class="form-group">
          <label class="form-label" for="wh_format">Payload format</label>
          <select id="wh_format" class="form-input" name="format">
            <option value="slack">Slack (attachment)</option>
            <option value="discord">Discord (embed)</option>
            <option value="generic" selected>Generic JSON</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="wh_terms">Filter: watch terms <span style="font-weight:400">(optional)</span></label>
          <input id="wh_terms" class="form-input" type="text" name="filter_terms"
            maxlength="1600" autocomplete="off" placeholder="CVE-2025, critical, zero-day, ransomware">
          <p class="form-hint">Comma-separated. Article title or summary must contain at least one term (case-insensitive).</p>
        </div>

        <?php if ($categories !== []): ?>
          <div class="form-group">
            <span class="form-label">Filter: categories <span style="font-weight:400">(optional)</span></span>
            <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-top:0.3rem">
              <?php foreach ($categories as $cat): ?>
                <label style="display:inline-flex;align-items:center;gap:0.35rem;font-size:0.875rem;cursor:pointer">
                  <input type="checkbox" name="filter_categories[]" value="<?= Html::e($cat['slug']) ?>">
                  <?= Html::e($cat['name']) ?>
                </label>
              <?php endforeach; ?>
            </div>
            <p class="form-hint">Article source must belong to at least one checked category.</p>
          </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary">Add webhook</button>
      </form>
    <?php else: ?>
      <p class="form-hint">Maximum of 10 webhooks reached.</p>
    <?php endif; ?>
  </section>

  <?php if ($recentLog !== []): ?>
  <section class="settings-section" style="margin-top:2rem">
    <h2 class="settings-section-title">Recent deliveries</h2>
    <table style="width:100%;font-size:0.8125rem;border-collapse:collapse">
      <thead>
        <tr style="text-align:left;border-bottom:1px solid var(--color-border)">
          <th style="padding:0.35rem 0.5rem">Article</th>
          <th style="padding:0.35rem 0.5rem">Status</th>
          <th style="padding:0.35rem 0.5rem">When</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentLog as $row):
          $ok = in_array($row['status'], ['ok', 'retry_ok'], true);
        ?>
          <tr style="border-bottom:1px solid var(--color-border)">
            <td style="padding:0.35rem 0.5rem;max-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= Html::e(mb_substr($row['article_title'], 0, 80)) ?></td>
            <td style="padding:0.35rem 0.5rem">
              <span class="source-badge" style="--badge-color:<?= $ok ? '#16a34a' : '#dc2626' ?>">
                <?= Html::e($row['status']) ?>
                <?php if (!$ok && $row['http_status']): ?>(<?= (int) $row['http_status'] ?>)<?php endif; ?>
              </span>
            </td>
            <td style="padding:0.35rem 0.5rem;white-space:nowrap;color:var(--color-text-muted)"><?= Html::e(substr($row['created_at'], 0, 16)) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <?php endif; ?>

</div>
