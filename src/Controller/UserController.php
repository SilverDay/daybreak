<?php
declare(strict_types=1);

namespace Daybreak\Controller;

use Daybreak\Security\Csrf;
use Daybreak\Security\Html;
use Daybreak\Service\AuthService;

/** Account settings, DSGVO data export, and account deletion. */
final class UserController
{
    public function showAccount(array $args = []): void
    {
        AuthService::requireAuth();
        $user      = AuthService::currentUser();
        $title     = 'Account settings';
        $activeNav = 'settings';

        $categories      = [];
        $ransomlookItems = [];
        $cveItems        = [];
        $windowDays      = 1;
        $activeCategory  = null;

        header('Content-Type: text/html; charset=utf-8');
        include DB_ROOT . '/src/View/layout.php';
        include DB_ROOT . '/src/View/user/account.php';
        include DB_ROOT . '/src/View/layout_end.php';
    }

    public function handleAccount(array $args = []): void
    {
        AuthService::requireAuth();
        Csrf::check();

        $user   = AuthService::currentUser();
        $userId = (int) $user['id'];
        $action = $_POST['action'] ?? '';

        if ($action === 'name') {
            $name = trim($_POST['display_name'] ?? '');
            if (!AuthService::updateDisplayName($userId, $name)) {
                $_SESSION['flash_error'] = 'Display name cannot be empty.';
            } else {
                $_SESSION['flash'] = 'Display name updated.';
            }
        } elseif ($action === 'password') {
            $current = $_POST['current_password'] ?? '';
            $new     = $_POST['new_password']     ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if ($new !== $confirm) {
                $_SESSION['flash_error'] = 'New passwords do not match.';
            } elseif (mb_strlen($new) < 12) {
                $_SESSION['flash_error'] = 'Password must be at least 12 characters.';
            } elseif (!AuthService::changePassword($userId, $current, $new)) {
                $_SESSION['flash_error'] = 'Current password is incorrect.';
            } else {
                $_SESSION['flash'] = 'Password updated.';
            }
        }

        header('Location: /settings/account');
        exit;
    }

    public function deleteAccount(array $args = []): void
    {
        AuthService::requireAuth();
        Csrf::check();

        $user     = AuthService::currentUser();
        $userId   = (int) $user['id'];
        $confirm  = trim($_POST['confirm'] ?? '');

        if ($confirm !== 'DELETE') {
            $_SESSION['flash_error'] = 'Type DELETE to confirm account deletion.';
            header('Location: /settings/account');
            exit;
        }

        AuthService::logout();
        AuthService::deleteAccount($userId);

        // Restart a fresh anonymous session.
        session_start();
        $_SESSION['flash'] = 'Your account has been permanently deleted.';
        header('Location: /');
        exit;
    }

    public function export(array $args = []): void
    {
        AuthService::requireAuth();

        $user = AuthService::currentUser();
        $data = AuthService::exportData((int) $user['id']);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="daybreak-data-export.json"');
        header('Content-Length: ' . strlen($json));
        echo $json;
    }
}
