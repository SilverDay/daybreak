<?php

declare(strict_types=1); ?>
<div class="legal-page">
    <div class="legal-content">
        <h1>About Daybreak</h1>
        <p class="legal-updated">Daybreak is a self-hosted security news aggregator built for people who need to stay on
            top of the threat landscape without wading through noise. It pulls headlines from a curated list of
            trusted security and privacy sources, supplements them with live ransomware victim data and recent CVEs, and
            presents everything in one fast, server-rendered interface.</p>

        <p>No tracking, no algorithmic feed manipulation, no ads. Just the news, as it lands.</p>

        <h2>News Feed</h2>
        <p>The main feed aggregates articles from security blogs, threat intelligence outlets, and vulnerability
            disclosure channels. Articles are fetched in the background on a regular schedule; the page you read is
            always served from cache, so it loads instantly.</p>
        <p>You can narrow the feed by <strong>category</strong> — Critical / Patch Now, Threat Intel, Strategic,
            DACH Corner, Privacy, Ransomware, EU / Policy — using the filter bar, and adjust the
            <strong>time window</strong> to show the last 24&nbsp;hours, 3&nbsp;days, 7&nbsp;days, or
            30&nbsp;days.</p>

        <h2>Ransomware Activity</h2>
        <p>A live sidebar widget shows the latest ransomware victim postings sourced from
            <a href="https://www.ransomlook.io/" target="_blank" rel="noopener noreferrer nofollow">ransomlook.io</a>
            (data used under <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank"
                rel="noopener noreferrer nofollow">CC&nbsp;BY&nbsp;4.0</a>). The widget respects the same time window as
            the main feed.</p>

        <h2>CVE Tracker</h2>
        <p>A second sidebar widget surfaces recent vulnerabilities from the NIST National Vulnerability Database,
            cross-referenced with the
            <a href="https://www.cisa.gov/known-exploited-vulnerabilities-catalog" target="_blank"
                rel="noopener noreferrer nofollow">CISA Known Exploited Vulnerabilities catalogue</a>. Each entry shows
            its CVSS severity and EPSS exploitation-probability score where available.</p>

        <h2>Sources Directory</h2>
        <p>The <a href="/sources">Sources</a> page lists every active feed Daybreak monitors, along with its category
            and current health status. You can see which sources are freshly updated, which are quiet, and which have
            been temporarily disabled due to repeated fetch failures.</p>

        <h2>Full-Text Search</h2>
        <p>The <a href="/search">Search</a> page lets you run keyword searches across all cached article titles and
            summaries. Results are ranked by recency and can be filtered by category.</p>

        <h2>Personalised Feed <span class="legal-lang">(account required)</span></h2>
        <p>Registered users get a <strong>My Feed</strong> view that shows only articles from the sources they have
            chosen to follow. You can subscribe and unsubscribe from individual sources at any time under
            <a href="/settings/sources">Settings → Sources</a>.</p>

        <h2>Starred Articles <span class="legal-lang">(account required)</span></h2>
        <p>Star any article to save it for later. Your starred items are collected on the
            <a href="/starred">Starred</a> page and persist across sessions.</p>

        <h2>Watch Terms <span class="legal-lang">(account required)</span></h2>
        <p>Add keywords under <a href="/settings/watch">Settings → Watch terms</a> to keep tabs on specific topics,
            threat actors, CVE IDs, or vendor names. Matching articles are highlighted in your feed, and any matches
            from the last 24&nbsp;hours appear in a dedicated <em>Alerts</em> block at the top of My&nbsp;Feed.</p>

        <h2>Webhooks <span class="legal-lang">(account required)</span></h2>
        <p>Push new articles to <strong>Slack</strong>, <strong>Discord</strong>, <strong>Microsoft Teams</strong>, or any HTTP endpoint the moment they
            land in the feed. Configure one or more webhooks under
            <a href="/settings/webhooks">Settings → Webhooks</a>, with optional filters by keyword or category so you
            only receive the alerts that matter to you.</p>

        <h2>RSS Output <span class="legal-lang">(account required)</span></h2>
        <p>Daybreak exposes a personal RSS feed at <code>/feed/rss</code> so you can subscribe in any feed reader.
            The feed reflects your source subscriptions and is scoped to the last 7&nbsp;days of articles.</p>

        <h2>Suggest a Source</h2>
        <p>Know a quality security blog or feed that Daybreak is missing? Use the
            <a href="/suggest">Suggest</a> page to submit it. Suggestions are reviewed manually before a source is
            added.</p>

        <h2>Theme</h2>
        <p>Daybreak supports light and dark modes. The theme toggles instantly via the button in the top navigation
            and your preference is stored locally in the browser — no account needed.</p>

        <h2>Account &amp; Privacy</h2>
        <p>Creating an account is optional. If you do register, you can export all your data or permanently delete
            your account at any time under <a href="/settings/account">Settings → Account</a>. See the
            <a href="/privacy">Privacy Policy</a> for full details on what is stored and why.</p>

        <h2>Open Source</h2>
        <p>Daybreak is open source (MIT License). The source code is available at
            <a href="https://github.com/SilverDay/daybreak" target="_blank"
                rel="noopener noreferrer nofollow">github.com/SilverDay/daybreak</a>.</p>
    </div>
</div>
