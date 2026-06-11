<?php
declare(strict_types=1);

namespace Daybreak\Controller;

use Daybreak\Database;
use Daybreak\Security\Csrf;
use Daybreak\Security\Html;
use Daybreak\Service\AggregationService;
use Daybreak\Service\AuditLog;
use Daybreak\Service\AuthService;
use Daybreak\Service\FeedFetcher;

/**
 * Admin panel: source CRUD, suggestion moderation, feed health,
 * user admin, audit log. Every state-changing action writes audit_log.
 * All methods call AuthService::requireAdmin() as first step.
 */
final class AdminController
{
    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function dashboard(array $args = []): void
    {
        AuthService::requireAdmin();

        $sourceCounts = Database::query(
            "SELECT status, COUNT(*) AS n FROM sources GROUP BY status"
        )->fetchAll();
        $counts = [];
        foreach ($sourceCounts as $r) { $counts[$r['status']] = (int) $r['n']; }

        $pendingSuggestions = (int) Database::query(
            "SELECT COUNT(*) FROM source_suggestions WHERE status = 'pending'"
        )->fetchColumn();

        $totalArticles = (int) Database::query("SELECT COUNT(*) FROM articles")->fetchColumn();

        $sources = Database::query(
            "SELECT s.id, s.name, s.slug, s.status, s.consecutive_failures,
                    s.last_success_at, s.last_error, s.next_fetch_at,
                    c.name AS category_name,
                    (SELECT COUNT(*) FROM articles a
                     WHERE a.source_id = s.id
                       AND a.fetched_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS items_today
             FROM sources s
             LEFT JOIN source_categories c ON c.id = s.category_id
             ORDER BY s.status DESC, s.consecutive_failures DESC, s.name"
        )->fetchAll();

        $title     = 'Admin — Dashboard';
        $adminNav  = 'dashboard';
        include DB_ROOT . '/src/View/admin_layout.php';
        include DB_ROOT . '/src/View/admin/dashboard.php';
        include DB_ROOT . '/src/View/admin_layout_end.php';
    }

    // ── Sources ───────────────────────────────────────────────────────────────

    public function sourcesList(array $args = []): void
    {
        AuthService::requireAdmin();

        $sources = Database::query(
            "SELECT s.id, s.name, s.slug, s.status, s.adapter_type,
                    s.consecutive_failures, s.last_success_at, s.last_error,
                    c.name AS category_name
             FROM sources s
             LEFT JOIN source_categories c ON c.id = s.category_id
             ORDER BY c.sort_order, s.name"
        )->fetchAll();

        $title    = 'Admin — Sources';
        $adminNav = 'sources';
        include DB_ROOT . '/src/View/admin_layout.php';
        include DB_ROOT . '/src/View/admin/sources/list.php';
        include DB_ROOT . '/src/View/admin_layout_end.php';
    }

    public function sourceCreate(array $args = []): void
    {
        AuthService::requireAdmin();
        $categories = Database::query('SELECT id, name FROM source_categories ORDER BY sort_order')->fetchAll();
        $source     = null; // null = create mode
        $title      = 'Admin — New source';
        $adminNav   = 'sources';
        include DB_ROOT . '/src/View/admin_layout.php';
        include DB_ROOT . '/src/View/admin/sources/edit.php';
        include DB_ROOT . '/src/View/admin_layout_end.php';
    }

    public function handleSourceCreate(array $args = []): void
    {
        AuthService::requireAdmin();
        Csrf::check();

        [$ok, $err, $id] = $this->saveSource(null, $_POST);
        if (!$ok) {
            $_SESSION['flash_error'] = $err;
            header('Location: /admin/sources/create');
            exit;
        }
        AuditLog::write('source.create', 'source', (string) $id);
        $_SESSION['flash'] = 'Source created.';
        header('Location: /admin/sources/' . $id);
        exit;
    }

    public function sourceEdit(array $args = []): void
    {
        AuthService::requireAdmin();
        $source = $this->requireSource((int) ($args['id'] ?? 0));

        $categories = Database::query('SELECT id, name FROM source_categories ORDER BY sort_order')->fetchAll();
        $recentLog  = Database::query(
            'SELECT status, http_status, items_found, items_new, duration_ms, error, created_at
             FROM fetch_log WHERE source_id = ? ORDER BY created_at DESC LIMIT 10',
            [(int) $source['id']]
        )->fetchAll();

        $title    = 'Admin — Edit source';
        $adminNav = 'sources';
        include DB_ROOT . '/src/View/admin_layout.php';
        include DB_ROOT . '/src/View/admin/sources/edit.php';
        include DB_ROOT . '/src/View/admin_layout_end.php';
    }

    public function handleSourceEdit(array $args = []): void
    {
        AuthService::requireAdmin();
        Csrf::check();

        $source = $this->requireSource((int) ($args['id'] ?? 0));
        $id     = (int) $source['id'];
        $action = $_POST['action'] ?? 'save';

        switch ($action) {
            case 'save':
                [$ok, $err] = $this->saveSource($id, $_POST);
                if (!$ok) {
                    $_SESSION['flash_error'] = $err;
                } else {
                    AuditLog::write('source.edit', 'source', (string) $id);
                    $_SESSION['flash'] = 'Source saved.';
                }
                break;

            case 'enable':
                Database::query("UPDATE sources SET status = 'active', consecutive_failures = 0 WHERE id = ?", [$id]);
                AuditLog::write('source.enable', 'source', (string) $id);
                $_SESSION['flash'] = 'Source enabled.';
                break;

            case 'disable':
                Database::query("UPDATE sources SET status = 'disabled' WHERE id = ?", [$id]);
                AuditLog::write('source.disable', 'source', (string) $id);
                $_SESSION['flash'] = 'Source disabled.';
                break;

            case 'reset':
                Database::query(
                    "UPDATE sources SET consecutive_failures = 0, last_error = NULL,
                     status = IF(status = 'auto_disabled', 'active', status)
                     WHERE id = ?",
                    [$id]
                );
                AuditLog::write('source.reset_failures', 'source', (string) $id);
                $_SESSION['flash'] = 'Failure counter reset.';
                break;

            case 'delete':
                $name = $source['name'];
                Database::query('DELETE FROM sources WHERE id = ?', [$id]);
                AuditLog::write('source.delete', 'source', $name);
                $_SESSION['flash'] = "Source \"{$name}\" deleted.";
                header('Location: /admin/sources');
                exit;
        }

        header('Location: /admin/sources/' . $id);
        exit;
    }

    public function sourceFetch(array $args = []): void
    {
        AuthService::requireAdmin();
        Csrf::check();

        $source = $this->requireSource((int) ($args['id'] ?? 0));
        $id     = (int) $source['id'];

        $svc = new AggregationService(new FeedFetcher());
        // Reload fresh from DB before running (source may have just been reset).
        $fresh = Database::query('SELECT * FROM sources WHERE id = ?', [$id])->fetch();
        $ok    = $fresh ? $svc->runSource($fresh) : false;

        AuditLog::write('source.fetch_now', 'source', (string) $id);
        $_SESSION[$ok ? 'flash' : 'flash_error'] = $ok ? 'Fetch completed.' : 'Fetch failed — check error column.';
        header('Location: /admin/sources/' . $id);
        exit;
    }

    // ── Suggestions ───────────────────────────────────────────────────────────

    public function suggestionsList(array $args = []): void
    {
        AuthService::requireAdmin();

        $suggestions = Database::query(
            "SELECT ss.*, u.display_name AS suggester_name, rv.display_name AS reviewer_name
             FROM source_suggestions ss
             LEFT JOIN users u  ON u.id  = ss.suggested_by
             LEFT JOIN users rv ON rv.id = ss.reviewed_by
             ORDER BY ss.status = 'pending' DESC, ss.created_at DESC"
        )->fetchAll();

        $categories = Database::query('SELECT id, name FROM source_categories ORDER BY sort_order')->fetchAll();

        $title    = 'Admin — Suggestions';
        $adminNav = 'suggestions';
        include DB_ROOT . '/src/View/admin_layout.php';
        include DB_ROOT . '/src/View/admin/suggestions/list.php';
        include DB_ROOT . '/src/View/admin_layout_end.php';
    }

    public function handleSuggestion(array $args = []): void
    {
        AuthService::requireAdmin();
        Csrf::check();

        $sgId   = (int) ($args['id'] ?? 0);
        $sg     = Database::query('SELECT * FROM source_suggestions WHERE id = ?', [$sgId])->fetch();
        if (!$sg) { http_response_code(404); echo 'Not found'; exit; }

        $admin  = AuthService::currentUser();
        $action = $_POST['action'] ?? '';

        if ($action === 'approve') {
            // Create a pending source from suggestion data.
            $catId = ($_POST['category_id'] ?? '') !== '' ? (int) $_POST['category_id'] : null;
            $slug  = $this->makeSlug((string) $sg['name']);
            Database::query(
                'INSERT INTO sources
                 (name, slug, homepage_url, feed_url, adapter_type, category_id,
                  attribution_text, status, fetch_interval_min, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?)',
                [
                    $sg['name'], $slug, $sg['homepage_url'],
                    $sg['feed_url'] ?: null,
                    $sg['detected_adapter'] ?: 'rss_atom',
                    $catId,
                    $sg['name'],           // attribution placeholder — admin can edit
                    'pending',
                    15,
                    (int) $admin['id'],
                ]
            );
            $newSourceId = (int) Database::lastInsertId();
            Database::query(
                "UPDATE source_suggestions SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?",
                [(int) $admin['id'], $sgId]
            );
            AuditLog::write('suggestion.approve', 'suggestion', (string) $sgId);
            $_SESSION['flash'] = 'Suggestion approved — source created in pending status. Edit and enable it under Sources.';
            header('Location: /admin/sources/' . $newSourceId);
            exit;
        }

        if ($action === 'reject') {
            $note = mb_substr(trim($_POST['review_note'] ?? ''), 0, 500);
            Database::query(
                "UPDATE source_suggestions SET status = 'rejected', reviewed_by = ?,
                 reviewed_at = NOW(), review_note = ? WHERE id = ?",
                [(int) $admin['id'], $note ?: null, $sgId]
            );
            AuditLog::write('suggestion.reject', 'suggestion', (string) $sgId);
            $_SESSION['flash'] = 'Suggestion rejected.';
        }

        header('Location: /admin/suggestions');
        exit;
    }

    // ── Users ─────────────────────────────────────────────────────────────────

    public function usersList(array $args = []): void
    {
        AuthService::requireAdmin();

        $users = Database::query(
            "SELECT id, email, display_name, role, status, last_login_at, created_at
             FROM users ORDER BY created_at DESC"
        )->fetchAll();

        $title    = 'Admin — Users';
        $adminNav = 'users';
        include DB_ROOT . '/src/View/admin_layout.php';
        include DB_ROOT . '/src/View/admin/users/list.php';
        include DB_ROOT . '/src/View/admin_layout_end.php';
    }

    public function handleUser(array $args = []): void
    {
        AuthService::requireAdmin();
        Csrf::check();

        $targetId = (int) ($args['id'] ?? 0);
        $me       = AuthService::currentUser();
        if ($targetId === (int) $me['id']) {
            $_SESSION['flash_error'] = 'You cannot modify your own account from the admin panel.';
            header('Location: /admin/users');
            exit;
        }

        $target = Database::query('SELECT id, email, display_name FROM users WHERE id = ?', [$targetId])->fetch();
        if (!$target) { http_response_code(404); echo 'Not found'; exit; }

        $action = $_POST['action'] ?? '';
        switch ($action) {
            case 'disable':
                Database::query("UPDATE users SET status = 'disabled' WHERE id = ?", [$targetId]);
                AuditLog::write('user.disable', 'user', (string) $targetId);
                $_SESSION['flash'] = 'Account disabled.';
                break;

            case 'enable':
                Database::query("UPDATE users SET status = 'active' WHERE id = ?", [$targetId]);
                AuditLog::write('user.enable', 'user', (string) $targetId);
                $_SESSION['flash'] = 'Account enabled.';
                break;

            case 'promote':
                Database::query("UPDATE users SET role = 'admin' WHERE id = ?", [$targetId]);
                AuditLog::write('user.promote', 'user', (string) $targetId);
                $_SESSION['flash'] = 'User promoted to admin.';
                break;

            case 'demote':
                Database::query("UPDATE users SET role = 'user' WHERE id = ?", [$targetId]);
                AuditLog::write('user.demote', 'user', (string) $targetId);
                $_SESSION['flash'] = 'Admin demoted to user.';
                break;

            case 'delete':
                $email = $target['email'];
                Database::query('DELETE FROM users WHERE id = ?', [$targetId]);
                AuditLog::write('user.delete', 'user', $email);
                $_SESSION['flash'] = "Account {$email} deleted.";
                break;
        }

        header('Location: /admin/users');
        exit;
    }

    // ── Audit log ─────────────────────────────────────────────────────────────

    public function auditList(array $args = []): void
    {
        AuthService::requireAdmin();

        $entries = Database::query(
            "SELECT al.id, al.action, al.target_type, al.target_id, al.created_at,
                    u.display_name AS actor
             FROM audit_log al
             LEFT JOIN users u ON u.id = al.user_id
             ORDER BY al.created_at DESC
             LIMIT 200"
        )->fetchAll();

        $title    = 'Admin — Audit log';
        $adminNav = 'audit';
        include DB_ROOT . '/src/View/admin_layout.php';
        include DB_ROOT . '/src/View/admin/audit/list.php';
        include DB_ROOT . '/src/View/admin_layout_end.php';
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function requireSource(int $id): array
    {
        $src = Database::query('SELECT * FROM sources WHERE id = ?', [$id])->fetch();
        if (!$src) {
            http_response_code(404);
            echo '<!doctype html><meta charset="utf-8"><title>Not Found</title><h1>Source not found</h1>';
            exit;
        }
        return $src;
    }

    /**
     * Validate and upsert a source row.
     * @return array{0:bool, 1:string, 2:int} [success, errorMessage, insertedId]
     */
    private function saveSource(?int $id, array $post): array
    {
        $name      = mb_substr(trim($post['name']           ?? ''), 0, 120);
        $slug      = mb_substr(trim($post['slug']           ?? ''), 0, 120);
        $homepage  = mb_substr(trim($post['homepage_url']   ?? ''), 0, 500);
        $feed      = mb_substr(trim($post['feed_url']       ?? ''), 0, 500);
        $adapter   = $post['adapter_type'] ?? 'rss_atom';
        $catId     = ($post['category_id'] ?? '') !== '' ? (int) $post['category_id'] : null;
        $attrib    = mb_substr(trim($post['attribution_text'] ?? ''), 0, 255);
        $license   = mb_substr(trim($post['license']        ?? ''), 0, 120);
        $interval  = max(1, min(1440, (int) ($post['fetch_interval_min'] ?? 15)));
        $fieldMap  = trim($post['field_map'] ?? '');

        if ($name === '') { return [false, 'Name is required.', 0]; }
        if ($slug === '') { $slug = $this->makeSlug($name); }
        if ($homepage === '') { return [false, 'Homepage URL is required.', 0]; }
        if ($attrib === '') { $attrib = $name; }

        $validAdapters = ['rss_atom', 'json_api', 'ransomlook', 'nvd', 'html_scrape'];
        if (!in_array($adapter, $validAdapters, true)) {
            return [false, 'Invalid adapter type.', 0];
        }

        // Validate field_map JSON if provided.
        $fieldMapJson = null;
        if ($fieldMap !== '' && $fieldMap !== '{}') {
            json_decode($fieldMap);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [false, 'Field map is not valid JSON.', 0];
            }
            $fieldMapJson = $fieldMap;
        }

        if ($id === null) {
            // Create — check for duplicate slug.
            $exists = Database::query('SELECT id FROM sources WHERE slug = ?', [$slug])->fetch();
            if ($exists) { $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4); }

            Database::query(
                'INSERT INTO sources
                 (name, slug, homepage_url, feed_url, adapter_type, category_id,
                  attribution_text, license, fetch_interval_min, field_map, status)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$name, $slug, $homepage, $feed ?: null, $adapter, $catId,
                 $attrib, $license ?: null, $interval, $fieldMapJson, 'pending']
            );
            return [true, '', (int) Database::lastInsertId()];
        }

        Database::query(
            'UPDATE sources SET name=?, slug=?, homepage_url=?, feed_url=?, adapter_type=?,
             category_id=?, attribution_text=?, license=?, fetch_interval_min=?, field_map=?
             WHERE id=?',
            [$name, $slug, $homepage, $feed ?: null, $adapter, $catId,
             $attrib, $license ?: null, $interval, $fieldMapJson, $id]
        );
        return [true, '', $id];
    }

    private function makeSlug(string $name): string
    {
        $s = mb_strtolower($name);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? $s;
        return trim($s, '-');
    }
}
