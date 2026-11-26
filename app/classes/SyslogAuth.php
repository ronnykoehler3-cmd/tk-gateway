<?php
declare(strict_types=1);

final class SyslogAuth
{
    private const AUTH_FILE = '/etc/tkgateway/syslog-auth.php';
    private const ATTEMPT_DIR = '/var/lib/tkgateway/syslog-auth-failures';
    private const SESSION_NAME = 'TKGatewaySyslog';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        session_name(self::SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header("Content-Security-Policy: default-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    }

    public static function csrfToken(): string
    {
        self::start();
        if (empty($_SESSION['syslog_csrf'])) $_SESSION['syslog_csrf'] = bin2hex(random_bytes(32));
        return (string)$_SESSION['syslog_csrf'];
    }

    public static function login(string $password, string $csrf): bool
    {
        self::start();
        if (!hash_equals((string)($_SESSION['syslog_csrf'] ?? ''), $csrf)) return false;
        if (!is_file(self::AUTH_FILE) || !is_readable(self::AUTH_FILE)) return false;
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $attemptFile = self::ATTEMPT_DIR . '/' . hash('sha256', $ip) . '.json';
        $attempts = @fopen($attemptFile, 'c+');
        if (!$attempts || !flock($attempts, LOCK_EX)) {
            if (is_resource($attempts)) fclose($attempts);
            return false;
        }
        $state = json_decode((string)stream_get_contents($attempts), true);
        if (!is_array($state) || (int)($state['since'] ?? 0) < time() - 900) $state = ['since' => time(), 'count' => 0];
        if ((int)$state['count'] >= 5) { flock($attempts, LOCK_UN); fclose($attempts); return false; }
        $hash = require self::AUTH_FILE;
        $valid = is_string($hash) && password_verify($password, $hash);
        $state['count'] = $valid ? 0 : ((int)$state['count'] + 1);
        rewind($attempts);
        ftruncate($attempts, 0);
        fwrite($attempts, json_encode($state));
        fflush($attempts);
        flock($attempts, LOCK_UN);
        fclose($attempts);
        if (!$valid) return false;
        session_regenerate_id(true);
        $_SESSION['syslog_authenticated'] = true;
        $_SESSION['syslog_auth_time'] = time();
        $_SESSION['syslog_csrf'] = bin2hex(random_bytes(32));
        return true;
    }

    public static function requireLogin(bool $json = false): void
    {
        self::start();
        $valid = !empty($_SESSION['syslog_authenticated'])
            && (int)($_SESSION['syslog_auth_time'] ?? 0) > time() - 8 * 3600;
        if ($valid) return;
        unset($_SESSION['syslog_authenticated'], $_SESSION['syslog_auth_time']);
        if ($json) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Bitte zuerst anmelden.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: /syslog-login.php');
        exit;
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
    }
}
