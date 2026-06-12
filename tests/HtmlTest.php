<?php

declare(strict_types=1);

namespace Daybreak\Tests;

use Daybreak\Security\Html;

final class HtmlTest extends TestCase
{
    public function testEscapeEncodesHtmlSpecialChars(): void
    {
        $this->assertSame('&lt;script&gt;&quot;x&quot; &amp; y&lt;/script&gt;', Html::e('<script>"x" & y</script>'));
    }

    public function testSanitizeSummaryStripsTagsAndCollapsesWhitespace(): void
    {
        $summary = Html::sanitizeSummary(" <p>Hello\n<strong>world</strong> &amp; <em>team</em></p> ");

        $this->assertSame('Hello world & team', $summary);
    }

    public function testSanitizeSummaryTruncatesToRequestedLength(): void
    {
        $summary = Html::sanitizeSummary('abcdef', 5);

        $this->assertSame('abcd…', $summary);
    }
}
