-- Add cisa_kev adapter type and migrate the NVD source to CISA KEV.
ALTER TABLE sources
    MODIFY adapter_type ENUM('rss_atom','json_api','ransomlook','nvd','html_scrape','cisa_kev') NOT NULL;

UPDATE sources
SET adapter_type = 'cisa_kev',
    feed_url     = 'https://www.cisa.gov/sites/default/files/feeds/known_exploited_vulnerabilities.json',
    name         = 'CISA KEV',
    slug         = 'cisa-kev',
    homepage_url = 'https://www.cisa.gov/known-exploited-vulnerabilities-catalog',
    attribution_text = 'CISA Known Exploited Vulnerabilities',
    etag               = NULL,
    last_modified_hdr  = NULL,
    consecutive_failures = 0,
    last_error         = NULL,
    status             = 'active'
WHERE adapter_type = 'nvd';
