<?php

declare(strict_types=1);

use Daybreak\Security\Html;
use Daybreak\Security\Csrf;

$isCreate   = isset($isCreate) ? (bool) $isCreate : ($category === null);
$formErrors = $formErrors ?? [];
$c = $category ?? [
  'id'         => null,
  'name'       => '',
  'slug'       => '',
  'color'      => '',
  'sort_order' => 0,
];
?>
<div class="admin-page-header">
  <h1 class="admin-page-title"><?= $isCreate ? 'New category' : 'Edit category: ' . Html::e($c['name']) ?></h1>
  <a href="/admin/categories" class="btn btn-secondary btn-sm">← Categories</a>
</div>

<?php if (!empty($formErrors)): ?>
  <div class="flash-wrap flash-wrap--form">
    <?php foreach ($formErrors as $error): ?>
      <div class="flash flash-error"><?= Html::e((string) $error) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="settings-page settings-page--wide">

  <form method="post" action="<?= $isCreate ? '/admin/categories/create' : '/admin/categories/' . (int) $c['id'] ?>">
    <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
    <input type="hidden" name="action" value="save">

    <div class="settings-section">
      <h2 class="settings-section-title">Category details</h2>
      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label" for="cat-name">Name *</label>
          <input id="cat-name" class="form-input" type="text" name="name"
            value="<?= Html::e($c['name']) ?>" required maxlength="80">
        </div>
        <div class="form-group">
          <label class="form-label" for="cat-slug">Slug</label>
          <input id="cat-slug" class="form-input" type="text" name="slug"
            value="<?= Html::e($c['slug']) ?>" maxlength="80"
            placeholder="auto-generated from name"
            pattern="[a-z0-9\-]*" title="Lowercase letters, numbers, and hyphens only">
        </div>
      </div>
      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label" for="cat-color">Badge color</label>
          <div style="display:flex;gap:.5rem;align-items:center;">
            <input id="cat-color" class="form-input" type="color" name="color"
              value="<?= Html::e($c['color'] ?: '#3498db') ?>"
              style="width:3rem;height:2.25rem;padding:.2rem;cursor:pointer;">
            <input id="cat-color-hex" class="form-input" type="text" name="_color_hex_display"
              value="<?= Html::e($c['color'] ?: '#3498db') ?>"
              maxlength="7" placeholder="#rrggbb" readonly
              style="flex:1;font-family:monospace;">
          </div>
          <p class="form-hint">Hex color shown as source badge on article cards.</p>
        </div>
        <div class="form-group form-group--narrow">
          <label class="form-label" for="cat-order">Sort order</label>
          <input id="cat-order" class="form-input" type="number" name="sort_order"
            value="<?= (int) ($c['sort_order'] ?? 0) ?>" min="0" max="9999">
          <p class="form-hint">Lower numbers appear first in category navigation.</p>
        </div>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary"><?= $isCreate ? 'Create category' : 'Save changes' ?></button>
      <a href="/admin/categories<?= $isCreate ? '' : '/' . (int) $c['id'] ?>" class="btn btn-secondary">Cancel</a>
    </div>
  </form>

  <?php if (!$isCreate): ?>
    <div class="settings-section danger-zone">
      <h2 class="settings-section-title">Danger zone</h2>
      <p class="text-sm text-secondary">Deleting a category is blocked if any sources are still assigned to it.</p>
      <form method="post" action="/admin/categories/<?= (int) $c['id'] ?>"
        data-confirm="Delete category &quot;<?= Html::e($c['name']) ?>&quot;? This cannot be undone.">
        <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="delete">
        <button type="submit" class="btn btn-danger btn-sm">Delete category</button>
      </form>
    </div>
  <?php endif; ?>

</div>

<script nonce="<?= Html::e(\Daybreak\Security\SecurityHeaders::nonce()) ?>">
(function () {
  var picker = document.getElementById('cat-color');
  var hex    = document.getElementById('cat-color-hex');
  if (!picker || !hex) return;
  picker.addEventListener('input', function () {
    hex.value = picker.value;
    // Write the chosen value into the real color field the server reads.
    picker.name = 'color';
  });
  // On page load, make sure the color picker is the field that posts.
  picker.name = 'color';
  hex.removeAttribute('name');
}());
</script>
