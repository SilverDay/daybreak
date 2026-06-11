-- Daybreak — 002_seed_sources.sql
-- Initial source catalogue: 23 sources (18 live + 5 ⚠️ bot-blocked).
-- 2 deferred (BSI, IAPP) added via admin GUI. 2 excluded, 2 dropped.
-- Feed URLs verified by live HTTP probe 2026-06-11 (SPEC Appendix A).

SET NAMES utf8mb4;

-- Resolve category IDs once (avoids hard-coding auto-increment values).
SET @cat_critical   = (SELECT id FROM source_categories WHERE slug = 'critical');
SET @cat_threat     = (SELECT id FROM source_categories WHERE slug = 'threat-intel');
SET @cat_strategic  = (SELECT id FROM source_categories WHERE slug = 'strategic');
SET @cat_dach       = (SELECT id FROM source_categories WHERE slug = 'dach');
SET @cat_privacy    = (SELECT id FROM source_categories WHERE slug = 'privacy');
SET @cat_ransomware = (SELECT id FROM source_categories WHERE slug = 'ransomware');

-- INSERT IGNORE so this migration is safe to re-run.
INSERT IGNORE INTO sources
  (name, slug, homepage_url, feed_url, adapter_type, category_id,
   attribution_text, license, status, fetch_interval_min)
VALUES

-- ── Critical / Patch Now ─────────────────────────────────────────────────────
('CISA Advisories',
 'cisa-advisories',
 'https://www.cisa.gov/cybersecurity-advisories',
 'https://www.cisa.gov/cybersecurity-advisories/all.xml',
 'rss_atom', @cat_critical,
 'CISA', NULL, 'active', 30),

-- ⚠️ Cloudflare-fronted; may return 502 from datacenter IPs.
('Exploit-DB',
 'exploit-db',
 'https://www.exploit-db.com/',
 'https://www.exploit-db.com/rss.xml',
 'rss_atom', @cat_critical,
 'Exploit-DB', NULL, 'active', 30),

-- NVD uses the nvd adapter (CVE widget, not main feed).
('NIST NVD',
 'nist-nvd',
 'https://nvd.nist.gov/',
 'https://services.nvd.nist.gov/rest/json/cves/2.0',
 'nvd', @cat_critical,
 'NIST NVD', NULL, 'active', 30),

-- ── Threat Intel ─────────────────────────────────────────────────────────────
('The Record',
 'the-record',
 'https://therecord.media/',
 'https://therecord.media/feed/',
 'rss_atom', @cat_threat,
 'The Record · Recorded Future News', NULL, 'active', 15),

('OpenSSF Blog',
 'openssf-blog',
 'https://openssf.org/',
 'https://openssf.org/feed/',
 'rss_atom', @cat_threat,
 'OpenSSF', NULL, 'active', 60),

('Google Threat Intelligence',
 'google-threat-intel',
 'https://cloudblog.withgoogle.com/topics/threat-intelligence/',
 'https://cloudblog.withgoogle.com/topics/threat-intelligence/rss/',
 'rss_atom', @cat_threat,
 'Google Threat Intelligence (Mandiant)', NULL, 'active', 30),

('Microsoft Security Blog',
 'microsoft-security',
 'https://www.microsoft.com/en-us/security/blog/',
 'https://www.microsoft.com/en-us/security/blog/feed/',
 'rss_atom', @cat_threat,
 'Microsoft Security', NULL, 'active', 30),

('Talos Intelligence',
 'talos-intelligence',
 'https://blog.talosintelligence.com/',
 'https://blog.talosintelligence.com/rss/',
 'rss_atom', @cat_threat,
 'Cisco Talos', NULL, 'active', 30),

('Risky Business News',
 'risky-business-news',
 'https://news.risky.biz/',
 'https://news.risky.biz/rss/',
 'rss_atom', @cat_threat,
 'Risky Business', NULL, 'active', 15),

-- ⚠️ Sophos returns 503 from datacenter IPs.
('Sophos News',
 'sophos-news',
 'https://news.sophos.com/en-us/',
 'https://news.sophos.com/en-us/feed/',
 'rss_atom', @cat_threat,
 'Sophos', NULL, 'active', 30),

-- ── Strategic ────────────────────────────────────────────────────────────────
('BleepingComputer',
 'bleepingcomputer',
 'https://www.bleepingcomputer.com/',
 'https://www.bleepingcomputer.com/feed/',
 'rss_atom', @cat_strategic,
 'BleepingComputer', NULL, 'active', 15),

('The Hacker News',
 'the-hacker-news',
 'https://thehackernews.com/',
 'https://feeds.feedburner.com/TheHackersNews',
 'rss_atom', @cat_strategic,
 'The Hacker News', NULL, 'active', 15),

('Krebs on Security',
 'krebs-on-security',
 'https://krebsonsecurity.com/',
 'https://krebsonsecurity.com/feed/',
 'rss_atom', @cat_strategic,
 'Krebs on Security', NULL, 'active', 30),

('Schneier on Security',
 'schneier-on-security',
 'https://www.schneier.com/',
 'https://www.schneier.com/feed/atom/',
 'rss_atom', @cat_strategic,
 'Bruce Schneier', NULL, 'active', 60),

('Dark Reading',
 'dark-reading',
 'https://www.darkreading.com/',
 'https://www.darkreading.com/rss.xml',
 'rss_atom', @cat_strategic,
 'Dark Reading', NULL, 'active', 15),

('SecurityWeek',
 'securityweek',
 'https://www.securityweek.com/',
 'https://www.securityweek.com/feed/',
 'rss_atom', @cat_strategic,
 'SecurityWeek', NULL, 'active', 15),

('The Register (Security)',
 'the-register-security',
 'https://www.theregister.com/security/',
 'https://www.theregister.com/security/headlines.atom',
 'rss_atom', @cat_strategic,
 'The Register', NULL, 'active', 15),

-- ⚠️ Cloudflare 202 from datacenter IPs.
('CyberSecurityNews',
 'cybersecuritynews',
 'https://cybersecuritynews.com/',
 'https://cybersecuritynews.com/feed/',
 'rss_atom', @cat_strategic,
 'CyberSecurityNews', NULL, 'active', 30),

-- ⚠️ Returns 403 from datacenter IPs.
('Cybernews',
 'cybernews',
 'https://cybernews.com/',
 'https://cybernews.com/feed/',
 'rss_atom', @cat_strategic,
 'Cybernews', NULL, 'active', 30),

-- ── DACH Corner ──────────────────────────────────────────────────────────────
('heise online Security',
 'heise-security',
 'https://www.heise.de/security/',
 'https://www.heise.de/security/feed.xml',
 'rss_atom', @cat_dach,
 'heise online', NULL, 'active', 15),

-- ── Privacy ──────────────────────────────────────────────────────────────────
('Dr. Datenschutz',
 'dr-datenschutz',
 'https://www.dr-datenschutz.de/',
 'https://www.dr-datenschutz.de/feed/',
 'rss_atom', @cat_privacy,
 'Dr. Datenschutz', NULL, 'active', 60),

-- ⚠️ Returns 500 from datacenter IPs.
('Datenschutz-Guru',
 'datenschutz-guru',
 'https://www.datenschutz-guru.de/',
 'https://www.datenschutz-guru.de/feed/',
 'rss_atom', @cat_privacy,
 'Datenschutz-Guru', NULL, 'active', 60),

-- ── Ransomware ───────────────────────────────────────────────────────────────
-- ransomlook widget: items stored in articles but shown only in the widget rail.
('ransomlook.io',
 'ransomlook',
 'https://www.ransomlook.io/',
 'https://www.ransomlook.io/api/recent',
 'ransomlook', @cat_ransomware,
 'ransomlook.io', 'CC BY 4.0', 'active', 15);
