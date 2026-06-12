<?php

declare(strict_types=1);

use Daybreak\Security\Html;
?>
</section><!-- .feed-column -->

<?php if (($showWidgets ?? true) === true): ?>
<aside class="widget-rail" aria-label="Widgets">

  <div class="widget">
    <div class="widget-header">
      <h2 class="widget-title">Ransomware Activity</h2>
      <a href="https://www.ransomlook.io/"
        target="_blank" rel="noopener noreferrer nofollow"
        class="widget-attribution">Data: ransomlook.io (CC BY 4.0)</a>
    </div>
    <div class="widget-body">
      <?php if (empty($ransomlookItems ?? [])): ?>
        <p class="widget-empty">No recent activity — fetch pending.</p>
      <?php else: ?>
        <?php foreach (($ransomlookItems ?? []) as $ri): ?>
          <div class="ransom-item">
            <a href="<?= Html::e($ri['url']) ?>"
              target="_blank" rel="noopener noreferrer nofollow">
              <?= Html::e($ri['title']) ?>
            </a>
            <time><?= relativeTime($ri['published_at'] ?? null) ?></time>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="widget">
    <div class="widget-header">
      <h2 class="widget-title">Recent CVEs</h2>
      <a href="https://nvd.nist.gov/"
        target="_blank" rel="noopener noreferrer nofollow"
        class="widget-attribution">NVD / NIST</a>
    </div>
    <div class="widget-body">
      <?php if (empty($cveItems ?? [])): ?>
        <p class="widget-empty">No recent CVEs — fetch pending.</p>
      <?php else: ?>
        <?php foreach (($cveItems ?? []) as $ci): ?>
          <div class="cve-item">
            <a href="<?= Html::e($ci['url']) ?>"
              target="_blank" rel="noopener noreferrer nofollow">
              <?= Html::e($ci['title']) ?>
            </a>
            <?php if (!empty($ci['summary'])): ?>
              <p class="cve-summary"><?= Html::e(Html::sanitizeSummary((string) $ci['summary'], 180)) ?></p>
            <?php endif; ?>
            <?php if (!empty($ci['published_at'])): ?>
              <time><?= relativeTime($ci['published_at']) ?></time>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</aside><!-- .widget-rail -->
<?php endif; ?>

</div><!-- .content-grid -->
</main>

<footer class="site-footer">
  <p>
    &copy; <?= date('Y') ?> SilverDay Media &middot;
    <a href="/imprint">Imprint</a> &middot;
    <a href="/terms">Terms</a> &middot;
    <a href="/privacy">Privacy</a>
  </p>
</footer>
</body>

</html>
