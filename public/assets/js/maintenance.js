async function restartVPN()
{
    if(!confirm('VPN wirklich neu starten?'))
        return;

    const response =
        await fetch('/api/vpn_restart.php?t=' + Date.now());

    const result =
        await response.json();

    if(result.success)
        alert('VPN wurde erfolgreich neu gestartet.');
    else
        alert('Fehler beim Neustart der VPN Verbindung.');
}

async function restartGateway()
{
    if(!confirm('Gateway wirklich neu starten?'))
        return;

    const response =
        await fetch('/api/reboot.php?t=' + Date.now());

    const result =
        await response.json();

    if(result.success)
        alert('Gateway wird neu gestartet.');
    else
        alert('Fehler beim Neustart.');
}

async function createBackup()
{
    if(!confirm('Backup jetzt erstellen?'))
        return;

    const response =
        await fetch('/api/backup.php?t=' + Date.now());

    const result =
        await response.json();

    if(result.success)
    {
        alert(
            'Backup erfolgreich erstellt:\n' +
            result.file
        );

        loadBackups();
    }
    else
    {
        alert('Fehler beim Erstellen des Backups.');
    }
}

async function installUpdates()
{
    if(!confirm(
        'Alle verfügbaren Updates installieren?\n\n' +
        'Der Vorgang kann mehrere Minuten dauern und das Gateway muss anschließend eventuell neu gestartet werden.'
    ))
        return;

    const response =
        await fetch('/api/install_updates.php?t=' + Date.now());

    const result =
        await response.json();

    if(result.success)
    {
        alert(
            'Updateinstallation wurde gestartet.\n\n' +
            'Der Status kann einige Minuten benötigen.'
        );

        loadUpdates();
    }
    else
    {
        alert('Fehler beim Starten der Updates.');
    }
}

async function loadUpdates()
{
    const response =
        await fetch('/api/updates.php?t=' + Date.now());

    const updates =
        await response.json();

    let html = `
        <div class="card">
            <div class="card-title">
                Verfügbare Updates
            </div>

            <div class="card-value">
                ${updates.count}
            </div>

            <br>

            <strong>Letzte Prüfung:</strong><br>
            ${updates.checked}

            <br><br>

            <strong>Pakete:</strong><br>
    `;

    if(updates.count === 0)
    {
        html += `
            Keine Updates verfügbar.
        `;
    }
    else
    {
        updates.packages.forEach(function(pkg)
        {
            html += `
                • ${pkg}<br>
            `;
        });
    }

    html += `
        </div>
    `;

    document.getElementById(
        'update-list'
    ).innerHTML = html;
}

async function loadBackups()
{
    const response =
        await fetch(
            '/api/list_backups.php?t=' +
            Date.now()
        );

    const backups =
        await response.json();

    if(backups.length === 0)
    {
        document.getElementById(
            'backup-list'
        ).innerHTML =
        '<div class="card">Keine Backups vorhanden.</div>';

        return;
    }

    let html = `
        <table class="client-table">
            <thead>
                <tr>
                    <th>Datei</th>
                    <th>Größe</th>
                    <th>Datum</th>
                    <th>Download</th>
                </tr>
            </thead>
            <tbody>
    `;

    backups.forEach(function(backup)
    {
        html += `
            <tr>
                <td>${backup.name}</td>
                <td>${backup.size} MB</td>
                <td>${backup.date}</td>
                <td>
                    <a
                        href="/api/download_backup.php?file=${backup.name}"
                        target="_blank"
                        style="
                            color:#60a5fa;
                            text-decoration:none;
                        ">
                        Download
                    </a>
                </td>
            </tr>
        `;
    });

    html += `
            </tbody>
        </table>
    `;

    document.getElementById(
        'backup-list'
    ).innerHTML = html;
}

loadBackups();
loadUpdates();
