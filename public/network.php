<?php
include 'includes/header.php';
?>

<h1>Netzwerk</h1>

<div id="network-cards"></div>

<script src="/assets/js/network.js"></script>


<h2>Triple-WAN – Leitungsauswahl</h2>
<div id="triplewan-status">WAN-Status wird geladen ...</div>

<script>
(function () {
    const target = document.getElementById('triplewan-status');

    async function refreshTripleWan() {
        try {
            const response = await fetch(
                '/api/triplewan.php?t=' + Date.now(),
                {cache: 'no-store'}
            );

            if (!response.ok) throw new Error('HTTP ' + response.status);

            const data = await response.json();
            const container = document.createElement('div');
            container.className = 'cards';

            for (const wan of data.interfaces) {
                const card = document.createElement('div');
                card.className = 'card';

                const title = document.createElement('div');
                title.className = 'card-title';
                title.textContent = wan.name + ' · ' + wan.interface;

                const status = document.createElement('div');
                status.className = 'card-value';
                status.style.color = wan.link ? '#22c55e' : '#ef4444';
                status.textContent = wan.link ? 'Link vorhanden' : 'Kein Link';

                const details = document.createElement('div');
                details.style.marginTop = '10px';
                details.style.fontSize = '13px';
                details.style.lineHeight = '1.8';

                details.textContent =
                    'IP: ' + wan.ip +
                    ' | Gateway: ' + wan.gateway;

                const active = document.createElement('div');
                active.style.marginTop = '10px';
                active.style.fontWeight = 'bold';
                active.textContent = wan.active
                    ? '● AKTIVE LEITUNG'
                    : 'Reserveleitung';

                const button = document.createElement('button');
                button.style.cssText = 'display:block;width:100%;margin-top:15px;padding:12px;background:#2563eb;color:white;border:0;border-radius:6px;font-weight:bold;cursor:pointer;';
                const locked = data.manual !== 'AUTO';
                const selected = data.manual === wan.role;
                active.textContent = selected ? 'MANUELL GESPERRT' : (wan.active && !locked ? 'AKTIVE LEITUNG' : 'Reserveleitung');
                button.textContent = selected ? 'Deaktivieren / Automatik' : 'Fest aktivieren';
                button.disabled = locked && !selected;
                button.onclick = async () => {
                    button.disabled = true;
                    try {
                        const r = await fetch('/api/wan-control.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({mode:selected?'AUTO':wan.role})});
                        if (!r.ok) throw new Error('HTTP '+r.status);
                    } catch(e) { alert('Umschaltung nicht bestaetigt: '+e.message); }
                    refreshTripleWan();
                };
                card.append(title, status, details, active, button);
                container.appendChild(card);
            }

            target.replaceChildren(container);
        } catch (error) {
            target.textContent =
                'Triple-WAN-Status konnte nicht geladen werden: ' +
                error.message;
        }
    }

    refreshTripleWan();
    setInterval(refreshTripleWan, 5000);
})();
</script>

<?php
include 'includes/footer.php';
?>
