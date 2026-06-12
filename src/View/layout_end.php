<?php

declare(strict_types=1);

use Daybreak\Security\Html;

if (!function_exists('cveSeverityFromSummary')) {
  function cveSeverityFromSummary(?string $summary): string
  {
    if (!is_string($summary) || $summary === '') {
      return 'unknown';
    }

    if (preg_match('/\A\s*(CRITICAL|HIGH|MEDIUM|LOW)\b/i', $summary, $matches) === 1) {
      $severity = strtoupper((string) ($matches[1] ?? ''));
      if (in_array($severity, ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'], true)) {
        return strtolower($severity);
      }
    }

    return 'unknown';
  }
}
?>
</section><!-- .feed-column -->

<?php if (($showWidgets ?? true) === true): ?>
<aside class="widget-rail" aria-label="Widgets">

    <div class="widget">
        <div class="widget-header">
            <h2 class="widget-title">Ransomware Activity</h2>
            <a href="https://www.ransomlook.io/" target="_blank" rel="noopener noreferrer nofollow"
                class="widget-attribution">Data: ransomlook.io (CC BY 4.0)</a>
        </div>
        <div class="widget-body">
            <p class="widget-scroll-hint" aria-hidden="true">Scroll for more</p>
            <?php if (empty($ransomlookItems ?? [])): ?>
            <p class="widget-empty">No recent activity — fetch pending.</p>
            <?php else: ?>
            <?php foreach (($ransomlookItems ?? []) as $ri): ?>
            <div class="ransom-item">
                <a href="<?= Html::e($ri['url']) ?>" target="_blank" rel="noopener noreferrer nofollow">
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
            <a href="https://nvd.nist.gov/" target="_blank" rel="noopener noreferrer nofollow"
                class="widget-attribution">NVD / NIST</a>
        </div>
        <div class="widget-body">
            <p class="widget-scroll-hint" aria-hidden="true">Scroll for more</p>
            <?php if (empty($cveItems ?? [])): ?>
            <p class="widget-empty">No recent CVEs — fetch pending.</p>
            <?php else: ?>
            <?php foreach (($cveItems ?? []) as $ci): ?>
            <?php $severity = cveSeverityFromSummary((string) ($ci['summary'] ?? '')); ?>
            <?php $displaySummary = (string) ($ci['summary'] ?? ''); ?>
            <?php $displaySummary = preg_replace('/\A\s*(CRITICAL|HIGH|MEDIUM|LOW)\s*(\([^)]*\))?\s*(?:—|-)?\s*/i', '', $displaySummary) ?? $displaySummary; ?>
            <div class="cve-item cve-item--<?= Html::e($severity) ?>">
                <div class="cve-headline-row">
                    <a class="cve-headline" href="<?= Html::e($ci['url']) ?>" target="_blank"
                        rel="noopener noreferrer nofollow">
                        <?= Html::e($ci['title']) ?>
                    </a>
                    <span
                        class="cve-severity cve-severity--<?= Html::e($severity) ?>"><?= Html::e(strtoupper($severity === 'unknown' ? 'unscored' : $severity)) ?></span>
                </div>
                <?php if ($displaySummary !== ''): ?>
                <p class="cve-summary"><?= Html::e(Html::sanitizeSummary($displaySummary, 180)) ?></p>
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
        &copy; <?= date('Y') ?> <a href="https://silverday.media/" target="_blank"
            rel="noopener noreferrer nofollow">SilverDay Media</a> &middot;
        <a href="/imprint">Imprint</a> &middot;
        <a href="/terms">Terms</a> &middot;
        <a href="/privacy">Privacy</a><br>
        <a href="https://buymeacoffee.com/silverday" target="_blank" rel="noopener noreferrer nofollow">Buy me a Coffee
            and support my work</a>
    </p>
</footer>
</body>

</html>