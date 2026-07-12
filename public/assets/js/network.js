async function loadNetwork()
{
    const response = await fetch('/api/network.php?t=' + Date.now());
    const data = await response.json();

    document.getElementById('network-cards').innerHTML = `
    <div class="cards">

        <div class="card">
            <div class="card-title">Internet-IP</div>
            <div class="card-value">${data.wan_ip}</div>
        </div>

        <div class="card">
            <div class="card-title">LAN-IP</div>
            <div class="card-value">${data.lan_ip}</div>
        </div>

        <div class="card">
            <div class="card-title">Tunnel-IP</div>
            <div class="card-value">${data.vpn_ip}</div>
        </div>

        <div class="card">
            <div class="card-title">Deutsche Exit-IP</div>
            <div class="card-value">${data.exit_ip}</div>
        </div>

        <div class="card">
            <div class="card-title">Gateway</div>
            <div class="card-value">${data.gateway}</div>
        </div>

        <div class="card">
            <div class="card-title">DNS Server</div>
            <div class="card-value">${data.dns.join('<br>')}</div>
        </div>

        <div class="card">
            <div class="card-title">Latenz Deutschland</div>
            <div class="card-value">${data.ping} ms</div>
        </div>

    </div>
    `;
}

loadNetwork();
setInterval(loadNetwork,5000);
