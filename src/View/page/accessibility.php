<?php

declare(strict_types=1);
?>
<div class="legal-page">
    <div class="legal-content">
        <h1>Accessibility Statement <span class="legal-lang">(Barrierefreiheitserklaerung)</span></h1>
        <p class="legal-updated">Last updated: 2026-07-28</p>

        <h2>Scope</h2>
        <p>This accessibility statement applies to the Daybreak website at <a href="/">daybreak.silverday.de</a>.</p>

        <h2>Conformance target</h2>
        <p>Daybreak targets conformance with <strong>EN 301 549</strong> and <strong>WCAG 2.1 Level AA</strong> on a <strong>voluntary</strong>
            basis. Daybreak is a free, non-commercial personal project rather than a public-sector body or a commercial consumer service, so
            it falls outside the mandatory scope of Germany's BITV 2.0 (public-sector bodies only) and is not a service covered by the BFSG
            (Germany's implementation of the European Accessibility Act). This target is adopted because it's the right thing to do, not
            because a law requires it of this site.</p>

        <h2>Current status</h2>
        <p>Daybreak is <strong>partially conformant</strong> with WCAG 2.1 AA. Most core keyboard and semantic requirements are implemented,
            and automated scanning now covers both the public pages and the account-gated screens (settings, starred articles, My Feed,
            watch terms). "Partially conformant" reflects one remaining gap, not a known defect: automated tooling (Pa11y/axe) checks
            roughly a third of WCAG 2.1 AA success criteria and cannot verify criteria such as 1.3.2 (Meaningful Sequence),
            2.4.6 (Headings and Labels), or 3.3.x (Error Identification) on the registration and settings forms — a manual pass against
            those criteria is still outstanding.</p>

        <h2>Assessment method</h2>
        <p>The latest review was performed on 2026-07-28 with automated checks using Pa11y (axe runner), followed by manual contrast
            verification where automated results were inconclusive. Public pages tested:</p>
        <ul>
            <li><a href="/">/</a></li>
            <li><a href="/sources">/sources</a></li>
            <li><a href="/search">/search</a></li>
            <li><a href="/about">/about</a></li>
            <li><a href="/imprint">/imprint</a></li>
            <li><a href="/terms">/terms</a></li>
            <li><a href="/privacy">/privacy</a></li>
        </ul>
        <p>Account-gated pages were tested for the first time in this review, via a temporary test account created and removed solely
            for this purpose: My Feed, starred articles, and the account, security, source, widget, watch-term, and webhook settings
            screens.</p>

        <h2>Known issues</h2>
        <p>No open contrast or link-distinguishability defects are currently tracked. Two regressions were found and fixed on
            2026-07-28: source status badges relying on color alone at insufficient contrast
            (<code>freshness-badge--stale</code>, <code>freshness-badge--degraded</code>), and an inline settings-page link
            distinguishable only by color (WCAG 1.4.1) on the account settings screen.</p>
        <p>Automated scans continue to flag three native select controls in filter forms
            (<code>sources-category</code>, <code>search-days</code>, <code>search-category</code>) for WCAG 1.4.3.
            Manual verification confirms these render at approximately 17.9:1 text contrast and 10.4:1 border contrast,
            both well above the required thresholds; the automated flag is a known axe/Pa11y limitation with native
            <code>&lt;select&gt;</code> rendering rather than an actual defect. This item is considered resolved and will
            be re-checked if the automated flag changes.</p>

        <h2>Roadmap and timeline</h2>
        <p>A manual WCAG 2.1 AA pass covering the criteria automated tooling cannot verify — across both public and account-gated
            pages — is targeted for <strong>Q4 2026</strong>. This statement will be updated after each verification run.</p>

        <h2>Feedback and contact</h2>
        <p>If you find accessibility barriers, please contact:
            <a href="mailto:klingner@silverday.de">klingner@silverday.de</a>.
        </p>
        <p>Please include the page URL and a short description of the issue so we can reproduce it quickly.</p>

        <h2>Enforcement procedure</h2>
        <p>Because Daybreak sits outside the mandatory scope described above, no statutory accessibility enforcement or arbitration body
            (such as a BITV 2.0 Durchsetzungsstelle) has jurisdiction over this site — there is no formal escalation path beyond the
            contact above. If you report a barrier and aren't satisfied with the response, please reply and say so directly; genuine effort
            to fix real issues is the only enforcement mechanism that applies here.</p>

    </div>
</div>
