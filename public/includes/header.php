<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>TK Gateway</title>

    <link rel="stylesheet"
          href="/assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body>

<?php
$current = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">

    <div class="logo">
        TK Gateway
        <div class="version">
            RC1 Build <?php echo date('Ymd'); ?>
        </div>
    </div>

    <ul class="menu">

        <li>
            <a class="<?php echo $current=='dashboard.php'?'active':''; ?>"
               href="/dashboard.php">
                🏠 Dashboard
            </a>
        </li>

        <li>
            <a class="<?php echo $current=='clients.php'?'active':''; ?>"
               href="/clients.php">
                💻 Clients
            </a>
        </li>

        <li>
            <a class="<?php echo $current=='network.php'?'active':''; ?>"
               href="/network.php">
                🌐 Netzwerk
            </a>
        </li>

        <li>
            <a class="<?php echo $current=='vpn.php'?'active':''; ?>"
               href="/vpn.php">
                🔒 VPN Status
            </a>
        </li>

        <li>
            <a class="<?php echo $current=='vpn_profiles.php'?'active':''; ?>"
               href="/vpn_profiles.php">
                📡 VPN Profile
            </a>
        </li>

        <li>
            <a class="<?php echo $current=='vpn_import.php'?'active':''; ?>"
               href="/vpn_import.php">
                📥 VPN Import
            </a>
        </li>

        <li>
            <a class="<?php echo $current=='events.php'?'active':''; ?>"
               href="/events.php">
                📋 Ereignisse
            </a>
        </li>

        <li>
            <a class="<?php echo $current=='maintenance.php'?'active':''; ?>"
               href="/maintenance.php">
                🔧 Wartung
            </a>
        </li>

        <li>
            <a class="<?php echo $current=='settings.php'?'active':''; ?>"
               href="/settings.php">
                ⚙ Einstellungen
            </a>
        </li>

    </ul>

</div>

<div class="content">
