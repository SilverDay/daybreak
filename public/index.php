<?php
declare(strict_types=1);

/** Daybreak front controller. Apache DocumentRoot points here. */

require __DIR__ . '/../src/bootstrap.php';

use Daybreak\Router;
use Daybreak\Security\DbSessionHandler;
use Daybreak\Security\SecurityHeaders;
use Daybreak\Controller\AuthController;
use Daybreak\Controller\PublicController;
use Daybreak\Controller\UserController;

// DB-backed session handler must be registered before session_start().
session_set_save_handler(new DbSessionHandler(), true);

session_set_cookie_params([
    'lifetime' => 0,
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => ($_SERVER['HTTPS'] ?? '') === 'on',
]);
session_start();

SecurityHeaders::send();

$router = new Router();

// ── Public feed ────────────────────────────────────────────────────────────────
$router->get('/',                        [PublicController::class, 'home']);
$router->get('/category/{slug}',         [PublicController::class, 'home']);

// ── Registration ────────────────────────────────────────────────────────────────
$router->get( '/register',               [AuthController::class,  'showRegister']);
$router->post('/register',               [AuthController::class,  'handleRegister']);

// ── Email verification ─────────────────────────────────────────────────────────
$router->get('/verify/{token}',          [AuthController::class,  'verify']);

// ── Login / logout ─────────────────────────────────────────────────────────────
$router->get( '/login',                  [AuthController::class,  'showLogin']);
$router->post('/login',                  [AuthController::class,  'handleLogin']);
$router->post('/logout',                 [AuthController::class,  'logout']);

// ── Password reset ─────────────────────────────────────────────────────────────
$router->get( '/password/forgot',        [AuthController::class,  'showForgot']);
$router->post('/password/forgot',        [AuthController::class,  'handleForgot']);
$router->get( '/password/reset/{token}', [AuthController::class,  'showReset']);
$router->post('/password/reset/{token}', [AuthController::class,  'handleReset']);

// ── Account settings (auth required) ──────────────────────────────────────────
$router->get( '/settings/account',         [UserController::class,  'showAccount']);
$router->post('/settings/account',         [UserController::class,  'handleAccount']);
$router->post('/settings/account/delete',  [UserController::class,  'deleteAccount']);
$router->get( '/settings/export',          [UserController::class,  'export']);

// ── Phase 4/5 static pages (placeholders) ─────────────────────────────────────
// $router->get('/imprint', [PageController::class, 'imprint']);
// $router->get('/terms',   [PageController::class, 'terms']);
// $router->get('/privacy', [PageController::class, 'privacy']);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

try {
    $router->dispatch($method, $path);
} catch (\Throwable $e) {
    http_response_code(500);
    error_log('[daybreak] ' . $e->getMessage());
    echo 'Internal error';
}
