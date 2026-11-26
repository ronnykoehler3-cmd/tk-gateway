<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/classes/SyslogAuth.php';
SyslogAuth::start();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && hash_equals((string)($_SESSION['syslog_csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
    SyslogAuth::logout();
}
header('Location: /syslog-login.php');
