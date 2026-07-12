async function loadClients() {
    try {
        const response = await fetch('/api/clients.php?t=' + Date.now());
        const data = await response.json();

        let html = `
        <div class="cards" style="margin-bottom:20px;">
            <div class="card">
                <div class="card-title">Geräte gesamt</div>
                <div class="card-value">${data.count}</div>
            </div>

            <div class="card">
                <div class="card-title">Online</div>
                <div class="card-value">${data.online}</div>
            </div>
        </div>

        <table class="client-table">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Hostname</th>
                    <th>Hersteller</th>
                    <th>IP-Adresse</th>
                    <th>MAC-Adresse</th>
                    <th>Lease bis</th>
                </tr>
            </thead>
            <tbody>
        `;

        data.clients.forEach(client => {

            const status = client.online
                ? '<span class="online-dot"></span> Online'
                : '<span class="offline-dot"></span> Offline';

            html += `
                <tr>
                    <td>${status}</td>
                    <td>${client.hostname}</td>
                    <td>${client.vendor}</td>
                    <td>${client.ip}</td>
                    <td>${client.mac}</td>
                    <td>${client.expires}</td>
                </tr>
            `;
        });

        html += `
            </tbody>
        </table>
        `;

        document.getElementById('clients-table').innerHTML = html;
    }
    catch(error)
    {
        document.getElementById('clients-table').innerHTML =
            '<div class="card">Fehler beim Laden der Clients: ' + error + '</div>';

        console.error(error);
    }
}

loadClients();
setInterval(loadClients, 5000);
