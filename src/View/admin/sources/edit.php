<?php

declare(strict_types=1);

use Daybreak\Security\Html;
use Daybreak\Security\Csrf;

$source = $source ?? null;
$categories = $categories ?? [];
$isCreate = isset($isCreate) ? (bool) $isCreate : ($source === null);
$formErrors = $formErrors ?? [];
$previewResult = $previewResult ?? null;
$s = $source ?? [
  'id' => null,
  'name' => '',
  'slug' => '',
  'homepage_url' => '',
  'feed_url' => '',
  'adapter_type' => 'rss_atom',
  'category_id' => null,
  'attribution_text' => '',
  'license' => '',
  'language' => null,
  'fetch_interval_min' => 15,
  'field_map' => '',
  'status' => 'pending',
  'consecutive_failures' => 0,
  'last_error' => null,
];
$languageOptions = [
  'en' => 'English',
  'de' => 'German',
  'fr' => 'French',
  'es' => 'Spanish',
  'pt' => 'Portuguese',
  'nl' => 'Dutch',
  'it' => 'Italian',
  'ja' => 'Japanese',
  'zh' => 'Chinese',
  'ko' => 'Korean',
  'ru' => 'Russian',
  'ar' => 'Arabic',
  'pl' => 'Polish',
  'sv' => 'Swedish',
  'fi' => 'Finnish',
  'da' => 'Danish',
  'no' => 'Norwegian',
];
?>
<div class="admin-page-header">
  <h1 class="admin-page-title"><?= $isCreate ? 'New source' : 'Edit source: ' . Html::e($s['name']) ?></h1>
  <a href="/admin/sources" class="btn btn-secondary btn-sm">← Sources</a>
</div>

<?php if (!$isCreate): ?>
  <div class="admin-source-meta">
    <span>Status: <span class="status-pill status-pill--<?= Html::e($s['status']) ?>"><?= Html::e($s['status']) ?></span></span>
    <span>Failures: <strong <?= (int) $s['consecutive_failures'] > 0 ? 'class="text-danger"' : '' ?>><?= (int) $s['consecutive_failures'] ?></strong></span>
    <?php if ($s['last_error']): ?>
      <span class="text-danger text-sm">Last error: <?= Html::e(mb_substr($s['last_error'], 0, 120)) ?></span>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if (!empty($formErrors)): ?>
  <div class="flash-wrap" style="margin:0 0 1rem 0;">
    <?php foreach ($formErrors as $error): ?>
      <div class="flash flash-error"><?= Html::e((string) $error) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="settings-page settings-page--wide">

  <form method="post" action="<?= $isCreate ? '/admin/sources/create' : '/admin/sources/' . (int) $s['id'] ?>">
    <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
    <input type="hidden" name="action" value="save">

    <div class="settings-section">
      <h2 class="settings-section-title">Basic info</h2>
      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label" for="src-name">Name *</label>
          <input id="src-name" class="form-input" type="text" name="name"
            value="<?= Html::e($s['name']) ?>" required maxlength="120">
        </div>
        <div class="form-group">
          <label class="form-label" for="src-slug">Slug</label>
          <input id="src-slug" class="form-input" type="text" name="slug"
            value="<?= Html::e($s['slug']) ?>" maxlength="120"
            placeholder="auto-generated from name">
        </div>
      </div>
      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label" for="src-homepage">Homepage URL *</label>
          <input id="src-homepage" class="form-input" type="url" name="homepage_url"
            value="<?= Html::e($s['homepage_url']) ?>" required maxlength="500">
        </div>
        <div class="form-group">
          <label class="form-label" for="src-feed">Feed URL</label>
          <input id="src-feed" class="form-input" type="url" name="feed_url"
            value="<?= Html::e($s['feed_url'] ?? '') ?>" maxlength="500">
        </div>
      </div>
      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label" for="src-adapter">Adapter type</label>
          <select id="src-adapter" class="form-input" name="adapter_type">
            <?php foreach (['rss_atom', 'json_api', 'ransomlook', 'nvd', 'cisa_kev'] as $a): ?>
              <option value="<?= Html::e($a) ?>" <?= $s['adapter_type'] === $a ? ' selected' : '' ?>><?= Html::e($a) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="src-cat">Category</label>
          <select id="src-cat" class="form-input" name="category_id">
            <option value="">— none —</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= (int) $cat['id'] ?>" <?= (string) ($s['category_id'] ?? '') === (string) $cat['id'] ? ' selected' : '' ?>><?= Html::e($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <div class="settings-section">
      <h2 class="settings-section-title">Attribution &amp; schedule</h2>
      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label" for="src-attrib">Attribution text</label>
          <input id="src-attrib" class="form-input" type="text" name="attribution_text"
            value="<?= Html::e($s['attribution_text'] ?? '') ?>" maxlength="255">
        </div>
        <div class="form-group">
          <label class="form-label" for="src-license">License</label>
          <input id="src-license" class="form-input" type="text" name="license"
            value="<?= Html::e($s['license'] ?? '') ?>" maxlength="120"
            placeholder="e.g. CC BY 4.0">
        </div>
      </div>
      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label" for="src-language">Language</label>
          <select id="src-language" class="form-input" name="language">
            <option value="">— unspecified —</option>
            <?php foreach ($languageOptions as $code => $label): ?>
              <option value="<?= Html::e($code) ?>"<?= ($s['language'] ?? null) === $code ? ' selected' : '' ?>><?= Html::e($label) ?> (<?= Html::e($code) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group" style="max-width:240px">
        <label class="form-label" for="src-interval">Fetch interval (minutes)</label>
        <input id="src-interval" class="form-input" type="number" name="fetch_interval_min"
          value="<?= (int) ($s['fetch_interval_min'] ?? 15) ?>" min="1" max="1440">
      </div>
    </div>

    <div class="settings-section">
      <h2 class="settings-section-title">Advanced</h2>
      <div class="form-group">
        <label class="form-label" for="src-fieldmap">Field map (JSON, optional)</label>
        <textarea id="src-fieldmap" class="form-input form-textarea" name="field_map"
          placeholder='{"title": "headline", "url": "link"}'><?= Html::e($s['field_map'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary" name="action" value="save"><?= $isCreate ? 'Create source' : 'Save changes' ?></button>
      <button type="submit" class="btn btn-secondary" name="action" value="preview">Preview fetch</button>
      <a href="/admin/sources<?= $isCreate ? '' : '/' . (int) $s['id'] ?>" class="btn btn-secondary">Cancel</a>
    </div>
  </form>

  <?php if (is_array($previewResult)): ?>
    <div class="settings-section">
      <h2 class="settings-section-title">Preview result</h2>
      <p class="text-sm text-secondary">
        HTTP <?= (int) ($previewResult['http_status'] ?? 0) ?>
        <?php if (!empty($previewResult['not_modified'])): ?> · Not modified (304)<?php endif; ?>
          · Items parsed: <?= (int) ($previewResult['items_count'] ?? 0) ?>
      </p>

      <?php if (!empty($previewResult['errors'])): ?>
        <?php foreach ($previewResult['errors'] as $error): ?>
          <div class="flash flash-error" style="margin:0 0 .75rem 0;"><?= Html::e((string) $error) ?></div>
        <?php endforeach; ?>
      <?php elseif (!empty($previewResult['not_modified'])): ?>
        <div class="text-secondary text-sm" style="margin:0 0 .75rem 0;">Preview completed: source responded with not modified.</div>
      <?php else: ?>
        <div class="text-secondary text-sm" style="margin:0 0 .75rem 0;">Preview completed successfully.</div>
      <?php endif; ?>

      <?php foreach (($previewResult['warnings'] ?? []) as $warning): ?>
        <div class="text-secondary text-sm" style="margin:0 0 .5rem 0;">Warning: <?= Html::e((string) $warning) ?></div>
      <?php endforeach; ?>

      <?php if (!empty($previewResult['sample_items'])): ?>
        <div class="table-wrap">
          <table class="admin-table admin-table--sm">
            <thead>
              <tr>
                <th>Title</th>
                <th>URL</th>
                <th>Published</th>
                <th>Summary</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($previewResult['sample_items'] as $item): ?>
                <tr>
                  <td><?= Html::e($item->title) ?></td>
                  <td class="text-sm text-truncate"><a href="<?= Html::e($item->url) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= Html::e($item->url) ?></a></td>
                  <td class="text-sm"><?= Html::e($item->publishedAt?->format('Y-m-d H:i') ?? '—') ?></td>
                  <td class="text-sm text-truncate"><?= Html::e(mb_substr((string) ($item->summary ?? ''), 0, 140)) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php elseif (empty($previewResult['errors']) && empty($previewResult['not_modified'])): ?>
        <p class="text-secondary text-sm">No sample items available from this payload.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (!$isCreate): ?>
    <div class="settings-section">
      <h2 class="settings-section-title">Actions</h2>
      <div class="admin-action-row">
        <?php if ($s['status'] === 'disabled' || $s['status'] === 'auto_disabled'): ?>
          <form method="post" action="/admin/sources/<?= (int) $s['id'] ?>">
            <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="enable">
            <button type="submit" class="btn btn-primary btn-sm">Enable source</button>
          </form>
        <?php else: ?>
          <form method="post" action="/admin/sources/<?= (int) $s['id'] ?>">
            <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="disable">
            <button type="submit" class="btn btn-secondary btn-sm">Disable source</button>
          </form>
        <?php endif; ?>

        <form method="post" action="/admin/sources/<?= (int) $s['id'] ?>">
          <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
          <input type="hidden" name="action" value="reset">
          <button type="submit" class="btn btn-secondary btn-sm">Reset failure counter</button>
        </form>

        <form method="post" action="/admin/sources/<?= (int) $s['id'] ?>/fetch">
          <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
          <button type="submit" class="btn btn-secondary btn-sm">Fetch now</button>
        </form>
      </div>
    </div>

    <?php if (!empty($recentLog)): ?>
      <div class="settings-section">
        <h2 class="settings-section-title">Recent fetch log</h2>
        <div class="table-wrap">
          <table class="admin-table admin-table--sm">
            <thead>
              <tr>
                <th>Time</th>
                <th>Status</th>
                <th class="num">HTTP</th>
                <th class="num">Found</th>
                <th class="num">New</th>
                <th class="num">ms</th>
                <th>Error</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentLog as $row): ?>
                <tr>
                  <td class="text-sm"><?= Html::e((new DateTimeImmutable($row['created_at']))->format('M j H:i')) ?></td>
                  <td><span class="status-pill status-pill--<?= $row['status'] === 'ok' ? 'active' : 'auto_disabled' ?>"><?= Html::e($row['status']) ?></span></td>
                  <td class="num"><?= $row['http_status'] ? (int) $row['http_status'] : '—' ?></td>
                  <td class="num"><?= (int) $row['items_found'] ?></td>
                  <td class="num"><?= (int) $row['items_new'] ?></td>
                  <td class="num"><?= (int) $row['duration_ms'] ?></td>
                  <td class="text-danger text-sm text-truncate"><?= Html::e(mb_substr($row['error'] ?? '', 0, 80)) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <div class="settings-section danger-zone">
      <h2 class="settings-section-title">Danger zone</h2>
      <form method="post" action="/admin/sources/<?= (int) $s['id'] ?>"
        data-confirm="Delete source &quot;<?= Html::e($s['name']) ?>&quot; and all its articles? This cannot be undone.">
        <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="delete">
        <button type="submit" class="btn btn-danger btn-sm">Delete source</button>
      </form>
    </div>
  <?php endif; ?>

</div>
