-- Add github_advisory adapter type and seed the GitHub Advisory Database source.
ALTER TABLE sources
    MODIFY adapter_type ENUM(
        'rss_atom','json_api','ransomlook','nvd','html_scrape','cisa_kev','github_advisory'
    ) NOT NULL;

INSERT INTO sources (
    name, slug, homepage_url, feed_url, adapter_type,
    category_id, attribution_text, fetch_interval_min, status
) VALUES (
    'GitHub Advisory Database',
    'github-advisory',
    'https://github.com/advisories',
    'https://api.github.com/advisories',
    'github_advisory',
    (SELECT id FROM source_categories WHERE slug = 'critical'),
    'GitHub Advisory Database',
    15,
    'active'
);
