<?php
declare(strict_types=1);

namespace Daybreak\Security;

/**
 * Output escaping + untrusted-feed sanitisation.
 * e()  -> escape any value for HTML output (use EVERYWHERE dynamic data is echoed).
 * sanitizeSummary() -> strip feed HTML down to a safe text/inline subset before storage.
 */
final class Html
{
    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Feed summaries are untrusted. For MVP we strip ALL tags and collapse whitespace,
     * then truncate. (If rich snippets are wanted later, replace with an allow-list
     * sanitiser such as HTML Purifier — never a naive regex.)
     */
    public static function sanitizeSummary(?string $html, int $maxLen = 400): string
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if (mb_strlen($text) > $maxLen) {
            $text = mb_substr($text, 0, $maxLen - 1) . '…';
        }
        return $text;
    }
}
