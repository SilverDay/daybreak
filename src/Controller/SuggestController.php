<?php

declare(strict_types=1);

namespace Daybreak\Controller;

use Daybreak\Security\Csrf;
use Daybreak\Security\Html;
use Daybreak\Service\AuthService;
use Daybreak\Service\SuggestionService;

final class SuggestController
{
    public function show(array $args = []): void
    {
        AuthService::requireAuth();

        $title     = 'Suggest a source';
        $activeNav = 'suggest';
        include DB_ROOT . '/src/View/layout.php';
        include DB_ROOT . '/src/View/suggest/index.php';
        include DB_ROOT . '/src/View/layout_end.php';
    }

    public function handle(array $args = []): void
    {
        AuthService::requireAuth();
        Csrf::check();

        $name     = mb_substr(trim($_POST['name']         ?? ''), 0, 120);
        $homepage = mb_substr(trim($_POST['homepage_url'] ?? ''), 0, 500);
        $feedUrl  = mb_substr(trim($_POST['feed_url']     ?? ''), 0, 500);
        $note     = mb_substr(trim($_POST['note']         ?? ''), 0, 500);

        if ($name === '' || $homepage === '') {
            $_SESSION['flash_error'] = 'Name and homepage URL are required.';
            header('Location: /suggest');
            exit;
        }

        if (!filter_var($homepage, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $homepage)) {
            $_SESSION['flash_error'] = 'Homepage URL must be a valid http/https URL.';
            header('Location: /suggest');
            exit;
        }

        $probe   = null;
        $probeOn = $feedUrl !== '' ? $feedUrl : $homepage;
        try {
            $probe = SuggestionService::probe($probeOn);
        } catch (\Throwable) {
            // probe failure is non-fatal
        }

        $user   = AuthService::currentUser();
        $userId = $user ? (int) $user['id'] : null;

        SuggestionService::submit(
            $userId,
            $name,
            $homepage,
            $feedUrl !== '' ? $feedUrl : ($probe['feed_url'] ?? null),
            $note !== '' ? $note : null,
            $probe
        );

        $_SESSION['flash'] = 'Thanks — your suggestion has been submitted for review.';
        header('Location: /suggest');
        exit;
    }
}
