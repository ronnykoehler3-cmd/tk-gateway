<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/classes/SyslogAuth.php';
SyslogAuth::start();
if (!empty($_SESSION['syslog_authenticated'])) { header('Location: /syslog.php'); exit; }
$message = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    if (SyslogAuth::login($password, (string)($_POST['csrf'] ?? ''))) {
        header('Location: /syslog.php'); exit;
    }
    $message = 'Anmeldung nicht möglich. Passwort prüfen und erneut versuchen.';
}
$csrf = htmlspecialchars(SyslogAuth::csrfToken(), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Syslog-Anmeldung – TK Gateway</title><link rel="stylesheet" href="/assets/css/syslog.css"></head>
<body class="login-page"><main class="login-card"><h1>Syslog &amp; Fehlerdiagnose</h1><p>Bitte mit dem eigens für die Diagnose eingerichteten Passwort anmelden.</p>
<?php if ($message !== ''): ?><p class="alert error"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post" autocomplete="on"><input type="hidden" name="csrf" value="<?= $csrf ?>"><label for="password">Passwort</label><input id="password" name="password" type="password" required autocomplete="current-password" autofocus><button type="submit">Anmelden</button></form></main></body></html>
