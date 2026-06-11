<?php
declare(strict_types=1);
use Daybreak\Security\Html;
use Daybreak\Security\Csrf;
?>
<div class="suggest-page">
  <div class="suggest-card">
    <h1 class="suggest-title">Suggest a news source</h1>
    <p class="suggest-desc">Know a security or privacy-focused blog, news site, or feed that belongs here? Submit it for review.</p>

    <form method="post" action="/suggest">
      <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">

      <div class="form-group">
        <label class="form-label" for="sg-name">Source name *</label>
        <input id="sg-name" class="form-input" type="text" name="name"
               required maxlength="120" autocomplete="off"
               placeholder="e.g. Krebs on Security">
      </div>

      <div class="form-group">
        <label class="form-label" for="sg-homepage">Homepage URL *</label>
        <input id="sg-homepage" class="form-input" type="url" name="homepage_url"
               required maxlength="500"
               placeholder="https://example.com">
        <span class="form-hint">We'll try to auto-detect a feed from this URL.</span>
      </div>

      <div class="form-group">
        <label class="form-label" for="sg-feed">Feed URL <span class="form-optional">(optional — if you know it)</span></label>
        <input id="sg-feed" class="form-input" type="url" name="feed_url"
               maxlength="500"
               placeholder="https://example.com/feed.xml">
      </div>

      <div class="form-group">
        <label class="form-label" for="sg-note">Notes <span class="form-optional">(optional)</span></label>
        <textarea id="sg-note" class="form-input form-textarea" name="note"
                  maxlength="500" rows="3"
                  placeholder="Why should this source be included?"></textarea>
      </div>

      <button type="submit" class="btn btn-primary btn-block">Submit suggestion</button>
    </form>
  </div>
</div>
