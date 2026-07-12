<?php
include 'includes/header.php';
?>

<h1>Wartung</h1>

<div class="cards">

    <div class="card">
        <div class="card-title">VPN Verbindung</div>

        <button onclick="restartVPN()" class="action-button">
            VPN neu starten
        </button>
    </div>

    <div class="card">
        <div class="card-title">Gateway</div>

        <button onclick="restartGateway()" class="action-button danger-button">
            Gateway neu starten
        </button>
    </div>

    <div class="card">
        <div class="card-title">Backup</div>

        <button onclick="createBackup()" class="action-button">
            Backup erstellen
        </button>
    </div>

    <div class="card">
        <div class="card-title">Systemupdates</div>

        <button onclick="installUpdates()" class="action-button">
            Updates installieren
        </button>
    </div>

</div>

<h2>Verfügbare Updates</h2>

<div id="update-list">
    Lade Updates...
</div>

<br>

<h2>Vorhandene Backups</h2>

<div id="backup-list">
    Lade Backups...
</div>

<script src="/assets/js/maintenance.js?v=<?php echo time(); ?>"></script>

<?php
include 'includes/footer.php';
?>
