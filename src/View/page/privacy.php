<?php

declare(strict_types=1); ?>
<div class="legal-page">
    <div class="legal-content">
        <h1>Privacy Policy <span class="legal-lang">(Datenschutzerklärung)</span></h1>
        <p class="legal-updated">Last updated: <?= date('Y') ?> · Pursuant to Art. 13/14 GDPR (DSGVO)</p>

        <h2>1. Controller</h2>
        <address>
            Klaus-E. Klingner<br>
            [Address — see Imprint]<br>
            E-mail: <a href="mailto:klingner@silverday.de">klingner@silverday.de</a>
        </address>

        <h2>2. Data collected and purposes</h2>
        <table class="legal-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Purpose</th>
                    <th>Legal basis (GDPR)</th>
                    <th>Retention</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Email address</td>
                    <td>Account creation, password reset, verification</td>
                    <td>Art. 6(1)(b) — contract</td>
                    <td>Until account deletion</td>
                </tr>
                <tr>
                    <td>Display name</td>
                    <td>Personalisation, shown in UI</td>
                    <td>Art. 6(1)(b)</td>
                    <td>Until account deletion</td>
                </tr>
                <tr>
                    <td>Argon2id password hash</td>
                    <td>Authentication</td>
                    <td>Art. 6(1)(b)</td>
                    <td>Until account deletion</td>
                </tr>
                <tr>
                    <td>Source preferences</td>
                    <td>Personalised feed</td>
                    <td>Art. 6(1)(b)</td>
                    <td>Until changed or account deleted</td>
                </tr>
                <tr>
                    <td>Last-seen timestamp</td>
                    <td>"Since last visit" feed view</td>
                    <td>Art. 6(1)(b)</td>
                    <td>Until account deletion</td>
                </tr>
                <tr>
                    <td>Hashed IP address (SHA-256 + key)</td>
                    <td>Login throttling, audit log</td>
                    <td>Art. 6(1)(f) — legitimate interest (security)</td>
                    <td>30 days (login attempts); audit log: 12 months</td>
                </tr>
                <tr>
                    <td>Session data</td>
                    <td>Maintaining login state</td>
                    <td>Art. 6(1)(b)</td>
                    <td>Until logout or session expiry</td>
                </tr>
                <tr>
                    <td>Source suggestions</td>
                    <td>Admin review queue</td>
                    <td>Art. 6(1)(b)</td>
                    <td>Until reviewed</td>
                </tr>
            </tbody>
        </table>

        <p>IP addresses are never stored in plaintext. They are hashed with a server-side key before storage, making
            them non-reversible under normal conditions.</p>

        <h2>3. No tracking, no analytics, no third-party scripts</h2>
        <p>Daybreak does not use Google Analytics, Facebook Pixel, or any other third-party tracking scripts. No cookies
            are set by third parties. The only cookie set is a server-side session cookie, strictly necessary for login
            functionality.</p>

        <h2>4. Outbound links</h2>
        <p>Clicking an article link takes you to a third-party website. That site's own privacy policy applies from that
            point. We do not pass your account data to external sites.</p>

        <h2>5. ransomlook.io data</h2>
        <p>Ransomware activity data is fetched from the public ransomlook.io API (CC BY 4.0). The ransomlook.io privacy
            policy applies to that service. We store only text-only metadata (group name, victim title, timestamp, link)
            — no screenshots or sensitive content.</p>

        <h2>6. NVD / NIST data</h2>
        <p>CVE data is retrieved from the NIST National Vulnerability Database public API. No personal data is
            transmitted during this fetch.</p>

        <h2>7. Hosting</h2>
        <p>Daybreak is hosted on a server located in the <strong>European Union</strong>. A Data Processing Agreement
            (DPA) is in place with the hosting provider. No data is transferred outside the EU/EEA.</p>

        <h2>8. Your rights (Art. 15–22 GDPR)</h2>
        <p>You have the right to:</p>
        <ul>
            <li><strong>Access</strong> your personal data (Art. 15) — use the "Export my data" function in account
                settings.</li>
            <li><strong>Rectification</strong> of inaccurate data (Art. 16) — change your display name in account
                settings.</li>
            <li><strong>Erasure</strong> ("right to be forgotten", Art. 17) — use "Delete account" in account settings
                or contact us.</li>
            <li><strong>Restriction</strong> of processing (Art. 18).</li>
            <li><strong>Data portability</strong> (Art. 20) — use the "Export my data" function (JSON format).</li>
            <li><strong>Object</strong> to processing based on legitimate interest (Art. 21).</li>
            <li><strong>Lodge a complaint</strong> with a supervisory authority. The lead supervisory authority for
                Germany is your <a href="https://www.bfdi.bund.de/" target="_blank"
                    rel="noopener noreferrer nofollow">state data protection authority (Landesbeauftragter für
                    Datenschutz)</a>.</li>
        </ul>
        <p>To exercise any right, contact: <a href="mailto:klingner@silverday.de">klingner@silverday.de</a></p>

        <h2>9. Changes to this policy</h2>
        <p>We may update this privacy policy as the service evolves. Material changes will be noted here with an updated
            date.</p>

    </div>
</div>
