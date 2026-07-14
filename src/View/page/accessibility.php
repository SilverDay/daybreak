<?php

declare(strict_types=1);
?>
<div class="legal-page">
    <div class="legal-content">
        <h1>Accessibility Statement <span class="legal-lang">(Barrierefreiheitserklaerung)</span></h1>
        <p class="legal-updated">Last updated: 2026-07-14</p>

        <h2>Scope</h2>
        <p>This accessibility statement applies to the Daybreak website at <a href="/">daybreak.silverday.de</a>.</p>

        <h2>Conformance target</h2>
        <p>Daybreak targets conformance with <strong>EN 301 549</strong> and <strong>WCAG 2.1 Level AA</strong>.</p>

        <h2>Current status</h2>
        <p>Daybreak is <strong>partially conformant</strong> with WCAG 2.1 AA. Most core keyboard and semantic requirements are implemented,
            and the majority of initially reported automated issues have been remediated.</p>

        <h2>Assessment method</h2>
        <p>The latest review was performed on 2026-07-14 with automated checks using Pa11y (axe runner) against representative public pages:</p>
        <ul>
            <li><a href="/">/</a></li>
            <li><a href="/sources">/sources</a></li>
            <li><a href="/search">/search</a></li>
            <li><a href="/about">/about</a></li>
            <li><a href="/imprint">/imprint</a></li>
            <li><a href="/terms">/terms</a></li>
            <li><a href="/privacy">/privacy</a></li>
        </ul>

        <h2>Known issues</h2>
        <ul>
            <li><strong>WCAG 1.4.3 Contrast (Minimum):</strong> Automated checks still report contrast warnings on three native select controls
                in filter forms (<code>sources-category</code>, <code>search-days</code>, <code>search-category</code>).
                These controls are under additional cross-browser review.</li>
        </ul>

        <h2>Roadmap and timeline</h2>
        <p>Remaining issues are scheduled for remediation in the next UI hardening cycle. Target completion for the open findings is Q3 2026.
            This statement will be updated after each verification run.</p>

        <h2>Feedback and contact</h2>
        <p>If you find accessibility barriers, please contact:
            <a href="mailto:klingner@silverday.de">klingner@silverday.de</a>.
        </p>
        <p>Please include the page URL and a short description of the issue so we can reproduce it quickly.</p>

        <h2>Enforcement procedure</h2>
        <p>If you do not receive a satisfactory response, you may contact the competent accessibility enforcement body in Germany under
            the applicable implementation of EU accessibility requirements.</p>

    </div>
</div>
