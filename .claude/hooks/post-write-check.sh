#!/usr/bin/env bash
# Fast grep-based security scan run after every Write/Edit. Warns (non-blocking)
# on patterns that violate Daybreak's non-negotiables. Tighten as needed.
set -euo pipefail
shopt -s globstar nullglob
fail=0
for f in src/**/*.php public/**/*.php bin/**/*.php; do
  head -3 "$f" | grep -q "declare(strict_types=1);" || { echo "WARN  $f: missing declare(strict_types=1)"; fail=1; }
  grep -nE '\$_(GET|POST|REQUEST|COOKIE)\[[^]]*\][^;]*\.\s*"|"\s*\.\s*\$_(GET|POST|REQUEST)' "$f" \
    && { echo "WARN  $f: possible string-built SQL/HTML from input"; fail=1; } || true
  if [[ "$f" != *FeedFetcher* ]]; then
    grep -nE 'file_get_contents\(\s*\$|curl_init\(\s*\$' "$f" \
      && { echo "WARN  $f: raw fetch of a variable URL — must go through FeedFetcher/SsrfGuard"; fail=1; } || true
  fi
  grep -nE 'echo\s+\$(row|article|item|source|user)\b' "$f" \
    && { echo "WARN  $f: echoing data without Html::e()"; fail=1; } || true
done
[ "$fail" -eq 0 ] && echo "post-write-check: clean" || echo "post-write-check: review warnings above"
exit 0
