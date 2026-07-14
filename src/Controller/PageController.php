<?php

declare(strict_types=1);

namespace Daybreak\Controller;

use Daybreak\Config;
use Daybreak\Security\Html;

/** Static legal / informational pages. */
final class PageController
{
    public function about(array $args = []): void
    {
        $title          = 'About';
        $activeNav      = 'about';
        $showWidgets    = false;
        $showFilterBar  = false;
        $seoDescription = 'Learn what Daybreak is, how it works, and what features are available.';
        include DB_ROOT . '/src/View/layout.php';
        include DB_ROOT . '/src/View/page/about.php';
        include DB_ROOT . '/src/View/layout_end.php';
    }

    public function accessibility(array $args = []): void
    {
        $title          = 'Accessibility Statement';
        $activeNav      = '';
        $showWidgets    = false;
        $showFilterBar  = false;
        $seoDescription = 'Accessibility statement for Daybreak according to EN 301 549 and WCAG 2.1 AA.';
        include DB_ROOT . '/src/View/layout.php';
        include DB_ROOT . '/src/View/page/accessibility.php';
        include DB_ROOT . '/src/View/layout_end.php';
    }

    public function imprint(array $args = []): void
    {
        $title         = 'Imprint';
        $activeNav     = '';
        $showWidgets   = false;
        $showFilterBar = false;
        include DB_ROOT . '/src/View/layout.php';
        include DB_ROOT . '/src/View/page/imprint.php';
        include DB_ROOT . '/src/View/layout_end.php';
    }

    public function terms(array $args = []): void
    {
        $title         = 'Terms of Service';
        $activeNav     = '';
        $showWidgets   = false;
        $showFilterBar = false;
        include DB_ROOT . '/src/View/layout.php';
        include DB_ROOT . '/src/View/page/terms.php';
        include DB_ROOT . '/src/View/layout_end.php';
    }

    public function privacy(array $args = []): void
    {
        $title         = 'Privacy Policy';
        $activeNav     = '';
        $showWidgets   = false;
        $showFilterBar = false;
        include DB_ROOT . '/src/View/layout.php';
        include DB_ROOT . '/src/View/page/privacy.php';
        include DB_ROOT . '/src/View/layout_end.php';
    }

    public function robots(array $args = []): void
    {
        $baseUrl = $this->baseUrl();
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\n";
        echo "Allow: /\n";
        echo "Disallow: /admin\n";
        echo "Disallow: /feed\n";
        echo "Disallow: /settings\n";
        echo "Disallow: /password\n";
        echo "Disallow: /search\n";
        echo "Disallow: /suggest\n";
        echo 'Sitemap: ' . $this->absoluteUrl($baseUrl, '/sitemap.xml') . "\n";
    }

    public function sitemap(array $args = []): void
    {
        $baseUrl = $this->baseUrl();
        $paths = ['/', '/about', '/accessibility', '/imprint', '/terms', '/privacy'];

        header('Content-Type: application/xml; charset=utf-8');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($paths as $path) {
            $loc = $this->absoluteUrl($baseUrl, $path);
            echo "  <url><loc>" . Html::e($loc) . "</loc></url>\n";
        }
        echo "</urlset>\n";
    }

    private function baseUrl(): string
    {
        $candidates = [
            trim((string) (Config::get('APP_BASE_URL', '') ?? '')),
            trim((string) (Config::get('APP_URL', '') ?? '')),
        ];

        foreach ($candidates as $configuredRaw) {
            if ($configuredRaw === '') {
                continue;
            }

            $parsedConfigured = parse_url($configuredRaw);
            if (!is_array($parsedConfigured)) {
                continue;
            }

            $scheme = (string) ($parsedConfigured['scheme'] ?? '');
            $host = (string) ($parsedConfigured['host'] ?? '');
            if (
                !in_array($scheme, ['http', 'https'], true)
                || $host === ''
                || preg_match('/\A[a-z0-9.-]+\z/i', $host) !== 1
            ) {
                continue;
            }

            $base = $scheme . '://' . $host;
            if (isset($parsedConfigured['port'])) {
                $base .= ':' . (int) $parsedConfigured['port'];
            }

            $path = (string) ($parsedConfigured['path'] ?? '');
            if ($path !== '' && $path !== '/') {
                $base .= '/' . trim($path, '/');
            }

            return rtrim($base, '/');
        }

        return '';
    }

    private function absoluteUrl(string $baseUrl, string $path): string
    {
        $normalizedPath = '/' . ltrim($path, '/');
        if ($baseUrl === '') {
            return $normalizedPath;
        }

        return rtrim($baseUrl, '/') . $normalizedPath;
    }
}
