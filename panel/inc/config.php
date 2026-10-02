<?php

const GOLDAPP_ADMIN_IDLE_TIMEOUT = 1800;
const GOLDAPP_ADMIN_ABSOLUTE_TIMEOUT = 43200;
const GOLDAPP_ADMIN_REGENERATE_INTERVAL = 900;

function goldapp_panel_is_https(): bool
{
    return !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
}

function goldapp_panel_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header("Content-Security-Policy: frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    if (goldapp_panel_is_https()) {
        header('Strict-Transport-Security: max-age=15552000');
    }
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => goldapp_panel_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

goldapp_panel_security_headers();

require __DIR__ . '/../../config.php';
require __DIR__ . '/../../function.php';

// Panel UI language strings (loaded from lang/fa.php via languagechange())
$textbotlang = languagechange();

function db_query(PDO $pdo, string $sql, array $params = []): PDOStatement
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_fetch(PDO $pdo, string $sql, array $params = []): ?array
{
    return db_query($pdo, $sql, $params)->fetch() ?: null;
}

function db_fetchAll(PDO $pdo, string $sql, array $params = []): array
{
    return db_query($pdo, $sql, $params)->fetchAll();
}

function db_count(PDO $pdo, string $sql, array $params = []): int
{
    return (int) db_query($pdo, $sql, $params)->fetchColumn();
}
function destroy_admin_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

function require_auth(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    global $pdo;

    if (empty($_SESSION['admin_user'])) {
        header('Location: login.php');
        exit;
    }

    $now = time();
    $loginTime = (int) ($_SESSION['login_time'] ?? 0);
    $lastActivity = (int) ($_SESSION['last_activity'] ?? $loginTime);

    $expired = $loginTime <= 0
        || ($now - $loginTime) > GOLDAPP_ADMIN_ABSOLUTE_TIMEOUT
        || ($lastActivity > 0 && ($now - $lastActivity) > GOLDAPP_ADMIN_IDLE_TIMEOUT);

    if ($expired) {
        destroy_admin_session();
        header('Location: login.php?expired=1');
        exit;
    }

    if (($now - (int) ($_SESSION['session_regenerated_at'] ?? $loginTime)) >= GOLDAPP_ADMIN_REGENERATE_INTERVAL) {
        session_regenerate_id(true);
        $_SESSION['session_regenerated_at'] = $now;
    }

    $_SESSION['last_activity'] = $now;

    try {
        $admin = db_fetch($pdo, "SELECT id_admin, rule FROM admin WHERE username = ?", [$_SESSION['admin_user']]);
        if (!$admin || $admin['rule'] !== 'administrator') {
            destroy_admin_session();
            header('Location: login.php');
            exit;
        }
    } catch (Exception $e) {
        destroy_admin_session();
        header('Location: login.php');
        exit;
    }
}

function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check_value(string $token): bool
{
    return hash_equals($_SESSION['csrf'] ?? '', $token);
}

function csrf_check_post(): void
{
    global $textbotlang;
    $token = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403);
        die($textbotlang['panel']['configInvalidRequest']);
    }
}

function csrf_check_get(): void
{
    global $textbotlang;
    $token = $_GET['_csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403);
        die($textbotlang['panel']['configInvalidRequest']);
    }
}

function flash(string $key, string $msg): void
{
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    $_SESSION["flash_{$key}"] = $msg;
}

function get_flash(string $key): ?string
{
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    $msg = $_SESSION["flash_{$key}"] ?? null;
    unset($_SESSION["flash_{$key}"]);
    return $msg;
}

function trunc(string $str, int $max = 30): string
{
    return mb_strlen($str, 'UTF-8') > $max
        ? mb_substr($str, 0, $max, 'UTF-8') . '…'
        : $str;
}

function safe_date($ts, string $fmt = 'Y/m/d'): string
{
    if (!$ts)
        return '—';
    if (!is_numeric($ts))
        return htmlspecialchars((string) $ts);
    return date($fmt, (int) $ts);
}
function check_login_rate(string $ip): bool
{
    $file = sys_get_temp_dir() . '/panel_login_' . md5($ip);
    $data = @json_decode(@file_get_contents($file) ?: '{}', true) ?: [];
    $now = time();
    $data = array_filter($data, fn($t) => ($now - $t) < 900);
    if (count($data) >= 10)
        return false;
    $data[] = $now;
    @file_put_contents($file, json_encode(array_values($data)), LOCK_EX);
    @chmod($file, 0600);
    return true;
}

function clear_login_rate(string $ip): void
{
    @unlink(sys_get_temp_dir() . '/panel_login_' . md5($ip));
}

function user_role_label(string $agent): string
{
    global $textbotlang;
    return match ($agent) {
        'n' => $textbotlang['panel']['configRoleN'],
        'n2' => $textbotlang['panel']['configRoleN2'],
        'all' => $textbotlang['panel']['configRoleAll'],
        default => $textbotlang['panel']['configRoleDefault'],
    };
}

function user_role_tag(string $agent): string
{
    return match ($agent) {
        'f' => 'tag-info',
        'n' => 'tag-info',
        'n2' => 'tag-warn',
        'all' => 'tag-ok',
        default => 'tag-plain',
    };
}
