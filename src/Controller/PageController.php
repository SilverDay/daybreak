<?php
declare(strict_types=1);

namespace Daybreak\Controller;

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
}
