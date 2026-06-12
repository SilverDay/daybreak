<?php

declare(strict_types=1);

namespace Daybreak\Controller;

use Daybreak\Config;
use Daybreak\Security\Html;

/** Static legal / informational pages. */
final class PageController
{
    public function imprint(array $args = []): void
    {
        $title     = 'Imprint';
        $activeNav = '';
        include DB_ROOT . '/src/View/layout.php';
        include DB_ROOT . '/src/View/page/imprint.php';
        include DB_ROOT . '/src/View/layout_end.php';
    }

    public function terms(array $args = []): void
    {
        $title     = 'Terms of Service';
        $activeNav = '';
        include DB_ROOT . '/src/View/layout.php';
        include DB_ROOT . '/src/View/page/terms.php';
        include DB_ROOT . '/src/View/layout_end.php';
    }

    public function privacy(array $args = []): void
    {
        $title     = 'Privacy Policy';
        $activeNav = '';
        include DB_ROOT . '/src/View/layout.php';
        include DB_ROOT . '/src/View/page/privacy.php';
        include DB_ROOT . '/src/View/layout_end.php';
    }

    public function robots(array $args = []): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\n";
        echo "Allow: /\n";
        echo "Disallow: /admin\n";
        echo "Disallow: /feed\n";
        echo "Disallow: /settings\n";
        echo "Disallow: /password\n";
        echo "Disallow: /search\n";
        echo "Disallow: /suggest\n";
        echo "Sitemap: /sitemap.xml\n";
    }

    public function sitemap(array $args = []): void
    {
        $baseUrl = $this->baseUrl();
        $paths = ['/', '/imprint', '/terms', '/privacy'];

        header('Content-Type: application/xml; charset=utf-8');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($paths as $path) {
            $loc = $baseUrl !== '' ? $baseUrl . $path : $path;
            echo "  <url><loc>" . Html::e($loc) . "</loc></url>\n";
        }
        echo "</urlset>\n";
    }

    private function baseUrl(): string
    {
        $configuredRaw = trim((string) (Config::get('APP_URL', '') ?? ''));
        if ($configuredRaw !== '') {
            $parsedConfigured = parse_url($configuredRaw);
            if (
                is_array($parsedConfigured)
                && in_array((string) ($parsedConfigured['scheme'] ?? ''), ['http', 'https'], true)
                && isset($parsedConfigured['host'])
                && is_string($parsedConfigured['host'])
                && preg_match('/\A[a-z0-9.-]+\z/i', $parsedConfigured['host']) === 1
            ) {
                $base = (string) $parsedConfigured['scheme'] . '://' . (string) $parsedConfigured['host'];
                if (isset($parsedConfigured['port'])) {
                    $base .= ':' . (int) $parsedConfigured['port'];
                }
                return $base;
            }
        }

        return '';
    }
}
