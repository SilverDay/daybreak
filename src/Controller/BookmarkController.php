<?php

declare(strict_types=1);

namespace Daybreak\Controller;

use Daybreak\Security\Csrf;
use Daybreak\Service\AuthService;
use Daybreak\Service\KiojuService;

final class BookmarkController
{
    public function save(array $args = []): void
    {
        AuthService::requireAuth();
        Csrf::check();

        $user = AuthService::currentUser();
        $userId = (int) ($user['id'] ?? 0);
        $url = trim((string) ($_POST['url'] ?? ''));
        $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 255);
        $origin = (string) ($_POST['origin'] ?? 'feed');
        $returnTo = $this->returnPathFromOrigin($origin);

        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL) || preg_match('#^https?://#i', $url) !== 1) {
            $_SESSION['flash_error'] = 'Invalid article URL.';
            header('Location: ' . $returnTo);
            exit;
        }

        try {
            $result = KiojuService::addBookmark($userId, $url, $title);
            if ($result['ok']) {
                $_SESSION['flash'] = $result['message'];
            } else {
                $_SESSION['flash_error'] = $result['message'];
            }
        } catch (\Throwable) {
            $_SESSION['flash_error'] = 'Could not save bookmark right now.';
        }

        header('Location: ' . $returnTo);
        exit;
    }

    private function returnPathFromOrigin(string $origin): string
    {
        if ($origin === 'public') {
            return '/';
        }
        if ($origin === 'search') {
            return '/search';
        }

        return '/feed?days=since';
    }
}
