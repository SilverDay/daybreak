<?php

declare(strict_types=1);

namespace Daybreak\Controller;

use Daybreak\Security\Csrf;
use Daybreak\Security\Html;
use Daybreak\Service\AuthService;

/** Handles registration, email verification, login, logout, and password reset. */
final class AuthController
{
    // ── Register ───────────────────────────────────────────────────────────────

    public function showRegister(array $args = []): void
    {
        $title = 'Create account';
        $this->renderAuth('auth/register.php', $title);
    }

    public function handleRegister(array $args = []): void
    {
        Csrf::check();

        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password']      ?? '';
        $name     = trim($_POST['name']     ?? '');

        try {
            AuthService::register($email, $password, $name);
        } catch (\InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: /register');
            exit;
        }

        // Generic message regardless of whether email was new or duplicate.
        $_SESSION['flash'] = 'If this email address is not already registered, '
            . 'you\'ll receive a verification link shortly. Please check your inbox.';
        header('Location: /login');
        exit;
    }

    // ── Email verification ─────────────────────────────────────────────────────

    public function verify(array $args = []): void
    {
        $rawToken = $args['token'] ?? '';
        $ok       = $rawToken !== '' && AuthService::verifyEmail($rawToken);

        $title = $ok ? 'Email verified' : 'Invalid link';
        $this->renderAuth('auth/verify.php', $title, ['verified' => $ok]);
    }

    // ── Login ──────────────────────────────────────────────────────────────────

    public function showLogin(array $args = []): void
    {
        $title = 'Sign in';
        $this->renderAuth('auth/login.php', $title);
    }

    public function handleLogin(array $args = []): void
    {
        Csrf::check();

        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password']      ?? '';
        $remember = isset($_POST['remember_me']);

        if (!AuthService::login($email, $password, $remember)) {
            $_SESSION['flash_error'] = 'Invalid credentials or account not verified. '
                . 'Please check your email and password.';
            header('Location: /login');
            exit;
        }

        header('Location: /feed');
        exit;
    }

    // ── Logout ─────────────────────────────────────────────────────────────────

    public function logout(array $args = []): void
    {
        Csrf::check();
        AuthService::logout();
        header('Location: /');
        exit;
    }

    // ── Forgot password ────────────────────────────────────────────────────────

    public function showForgot(array $args = []): void
    {
        $title = 'Forgot password';
        $this->renderAuth('auth/forgot.php', $title);
    }

    public function handleForgot(array $args = []): void
    {
        Csrf::check();

        $email = trim($_POST['email'] ?? '');
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            AuthService::forgotPassword($email);
        }

        // Always same response — no enumeration.
        $_SESSION['flash'] = 'If your email address is registered and active, '
            . 'you\'ll receive a password reset link shortly.';
        header('Location: /password/forgot');
        exit;
    }

    // ── Reset password ─────────────────────────────────────────────────────────

    public function showReset(array $args = []): void
    {
        $token = $args['token'] ?? '';
        $title = 'Set new password';
        $this->renderAuth('auth/reset.php', $title, ['token' => $token]);
    }

    public function handleReset(array $args = []): void
    {
        Csrf::check();

        $token    = $args['token']    ?? '';
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm']  ?? '';

        if ($password !== $confirm) {
            $_SESSION['flash_error'] = 'Passwords do not match.';
            header('Location: /password/reset/' . rawurlencode($token));
            exit;
        }
        if (mb_strlen($password) < 12) {
            $_SESSION['flash_error'] = 'Password must be at least 12 characters.';
            header('Location: /password/reset/' . rawurlencode($token));
            exit;
        }

        if (!AuthService::resetPassword($token, $password)) {
            $_SESSION['flash_error'] = 'This reset link is invalid or has expired. '
                . 'Please request a new one.';
            header('Location: /password/forgot');
            exit;
        }

        $_SESSION['flash'] = 'Your password has been reset. You can now sign in.';
        header('Location: /login');
        exit;
    }

    // ── Helper ─────────────────────────────────────────────────────────────────

    private function renderAuth(string $view, string $title, array $vars = []): void
    {
        extract($vars);
        header('Content-Type: text/html; charset=utf-8');
        include DB_ROOT . '/src/View/auth_layout.php';
        include DB_ROOT . '/src/View/' . $view;
        include DB_ROOT . '/src/View/auth_layout_end.php';
    }
}
