let currentClients = [];
let clientEditorOpen = false;

function esc(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

async function loadClients() {
    if (clientEditorOpen) return;

    try {
        const response = await fetch('/api/clients.php?t=' + Date.now(), {cache: 'no-store'});
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Clients konnten nicht geladen werden.');
        currentClients = data.clients || [];

        let html = `
        <div class="cards" style="margin-bottom:20px;">
            <div class="card"><div class="card-title">Geräte gesamt</div><div class="card-value">${data.count}</div></div>
            <div class="card"><div class="card-title">Online</div><div class="card-value">${data.online}</div></div>
        </div>
        <div class="card" style="margin-bottom:16px;">Diese Liste ist die zentrale Gerätequelle. Neue DHCP-Geräte erscheinen automatisch. Änderungen werden über die MAC-Adresse dauerhaft zugeordnet.</div>
        <table class="client-table">
            <thead><tr>
                <th>Status</th><th>Anzeigename</th><th>Hostname</th><th>Typ</th><th>Rufnummer</th>
                <th>IP-Adresse</th><th>MAC-Adresse</th><th>Hersteller</th><th>Bemerkung</th><th>Aktion</th>
            </tr></thead><tbody>`;

        currentClients.forEach((client, index) => {
            const status = client.online
                ? '<span class="online-dot"></span> Online'
                : '<span class="offline-dot"></span> Offline';
            const fixed = client.fixed_ip ? ' <span title="Feste DHCP-IP">🔒</span>' : '';
            html += `<tr>
                <td>${status}</td>
                <td><strong>${esc(client.display_name || client.name || '')}</strong></td>
                <td>${esc(client.hostname)}</td>
                <td>${esc(client.type)}</td>
                <td>${esc(client.phone)}</td>
                <td>${esc(client.managed_ip || client.ip)}${fixed}</td>
                <td>${esc(client.mac)}</td>
                <td>${esc(client.vendor)}</td>
                <td>${esc(client.note)}</td>
                <td><button type="button" onclick="editClient(${index})">Bearbeiten</button></td>
            </tr>`;
        });

        html += '</tbody></table><div id="client-editor"></div>';
        document.getElementById('clients-table').innerHTML = html;
    } catch (error) {
        document.getElementById('clients-table').innerHTML = '<div class="card">Fehler beim Laden der Clients: ' + esc(error.message || error) + '</div>';
        console.error(error);
    }
}

function closeClientEditor() {
    const editor = document.getElementById('client-editor');
    if (editor) editor.innerHTML = '';
    clientEditorOpen = false;
    loadClients();
}

function editClient(index) {
    const c = currentClients[index];
    if (!c) return;

    clientEditorOpen = true;

    document.getElementById('client-editor').innerHTML = `
      <div class="card" style="margin-top:18px;max-width:850px;">
        <h2>Gerät bearbeiten</h2>
        <form id="device-form">
          <input type="hidden" name="mac" value="${esc(c.mac)}">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <label>Anzeigename<br><input name="display_name" value="${esc(c.display_name)}" style="width:100%"></label>
            <label>Hostname<br><input name="hostname" value="${esc(c.hostname === 'Unbekannt' ? '' : c.hostname)}" style="width:100%"></label>
            <label>Gerätetyp<br><input name="type" value="${esc(c.type)}" placeholder="z. B. Fanvil Telefon" style="width:100%"></label>
            <label>Rufnummer / Nebenstelle<br><input name="phone" value="${esc(c.phone)}" style="width:100%"></label>
            <label>IP-Adresse<br>
              <span style="display:flex;gap:10px;align-items:center;">
                <input name="ip" value="${esc(c.managed_ip || c.ip)}" required style="flex:1">
                <label style="white-space:nowrap;"><input type="checkbox" name="fixed_ip" ${c.fixed_ip ? 'checked' : ''}> Feste IP</label>
              </span>
            </label>
            <label>MAC-Adresse<br><input value="${esc(c.mac)}" disabled style="width:100%"></label>
          </div>
          <label style="display:block;margin-top:12px;">Bemerkung<br><textarea name="note" rows="3" style="width:100%">${esc(c.note)}</textarea></label>
          <div style="margin-top:12px;display:flex;gap:10px;">
            <button type="submit">Speichern</button>
            <button type="button" onclick="closeClientEditor()">Abbrechen</button>
            <span id="device-save-status"></span>
          </div>
        </form>
      </div>`;

    document.getElementById('device-form').addEventListener('submit', saveClient);
    document.getElementById('client-editor').scrollIntoView({behavior: 'smooth', block: 'nearest'});
}

async function saveClient(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const status = document.getElementById('device-save-status');
    const fd = new FormData(form);
    const payload = {
        mac: fd.get('mac'),
        display_name: fd.get('display_name'),
        hostname: fd.get('hostname'),
        type: fd.get('type'),
        phone: fd.get('phone'),
        ip: fd.get('ip'),
        note: fd.get('note'),
        fixed_ip: fd.get('fixed_ip') === 'on'
    };

    status.textContent = 'Speichere…';
    try {
        const response = await fetch('/api/clients.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.error || 'Speichern fehlgeschlagen.');
        status.textContent = 'Gespeichert.';
        clientEditorOpen = false;
        await loadClients();
    } catch (error) {
        status.textContent = 'Fehler: ' + (error.message || error);
    }
}

loadClients();
setInterval(() => {
    if (!clientEditorOpen) loadClients();
}, 5000);
