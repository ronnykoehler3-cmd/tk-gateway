async function loadVPN()
{
    const vpnResponse =
        await fetch('/api/vpn.php?t=' + Date.now());

    const vpn =
        await vpnResponse.json();

    const statsResponse =
        await fetch('/api/vpn_stats.php?t=' + Date.now());

    const stats =
        await statsResponse.json();

    const color =
        vpn.status === 'active'
            ? '#22c55e'
            : '#ef4444';

    const text =
        vpn.status === 'active'
            ? 'Verbunden'
            : 'Getrennt';

    document.getElementById('vpn-cards').innerHTML = `
    <div class="cards">

        <div class="card">
            <div class="card-title">VPN Status</div>
            <div class="card-value" style="color:${color}">
                ${text}
            </div>
        </div>

        <div class="card">
            <div class="card-title">VPN Laufzeit</div>
            <div class="card-value">
                ${vpn.uptime}
            </div>
        </div>

        <div class="card">
            <div class="card-title">Deutsche Exit-IP</div>
            <div class="card-value">
                ${vpn.exit_ip}
            </div>
        </div>

        <div class="card">
            <div class="card-title">Latenz Deutschland</div>
            <div class="card-value">
                ${vpn.server_ping} ms
            </div>
        </div>

        <div class="card">
            <div class="card-title">Watchdog Eingriffe</div>
            <div class="card-value">
                ${stats.watchdog_restarts}
            </div>
        </div>

        <div class="card">
            <div class="card-title">Gateway Neustarts</div>
            <div class="card-value">
                ${stats.gateway_reboots}
            </div>
        </div>

        <div class="card">
            <div class="card-title">Letzte Störung</div>
            <div class="card-value">
                ${stats.last_failure}
            </div>
        </div>

        <div class="card">
            <div class="card-title">Letzte Wiederherstellung</div>
            <div class="card-value">
                ${stats.last_recovery}
            </div>
        </div>

        <div class="card">
            <div class="card-title">VPN Verbindung</div>
            <button onclick="restartVPN()" class="action-button">
                VPN neu starten
            </button>
        </div>

    </div>
    `;
}

async function restartVPN()
{
    if(!confirm('VPN wirklich neu starten?'))
        return;

    const response =
        await fetch('/api/vpn_restart.php');

    const result =
        await response.json();

    if(result.success)
        alert('VPN wurde erfolgreich neu gestartet.');
    else
        alert('Fehler beim Neustart.');
}

loadVPN();
setInterval(loadVPN,5000);
