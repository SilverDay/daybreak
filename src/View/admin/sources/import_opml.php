<?php

declare(strict_types=1);

use Daybreak\Security\Csrf;
use Daybreak\Security\Html;

?>
<div class="admin-page-header">
  <h1 class="admin-page-title">Import OPML</h1>
  <a href="/admin/sources" class="btn btn-secondary btn-sm">← Sources</a>
</div>

<div class="settings-page">
  <div class="settings-section">
    <p class="text-secondary" style="margin:0 0 1.25rem">
      Upload an OPML file exported from any RSS reader (Feedly, NewsBlur, Reeder, etc.).
      Each <code>&lt;outline xmlUrl="..."&gt;</code> entry creates a <strong>pending</strong>
      source with adapter <code>rss_atom</code>. Duplicates and URLs that fail the SSRF
      safety check are skipped automatically. Sources land in <em>pending</em> status —
      review and enable them individually under Sources.
    </p>
    <form method="post" action="/admin/sources/import-opml" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
      <div class="form-group">
        <label for="opml-file" class="form-label">OPML file <span class="text-secondary">(max&nbsp;2&nbsp;MB, .opml or .xml)</span></label>
        <input type="file" id="opml-file" name="opml" accept=".opml,.xml" required class="form-input">
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Import</button>
      </div>
    </form>
  </div>
</div>
