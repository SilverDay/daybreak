# Rule: templates / output escaping (applies to views)

- Every dynamic value rendered to HTML goes through `Daybreak\Security\Html::e()`.
- Feed titles/summaries are UNTRUSTED. They are sanitised on store
  (`Html::sanitizeSummary()`) and escaped on output. Never echo them raw.
- Outbound article links: `target="_blank" rel="noopener noreferrer nofollow"`.
- Every page includes the CSRF meta tag:
  `<meta name="csrf-token" content="<?= Html::e(Csrf::token()) ?>">`.
- ransomlook data must show "Data: ransomlook.io (CC BY 4.0)" attribution.
- No inline event handlers / inline `<script>` (CSP forbids them).
