
<style>
#tk-wan-dashboard {
    margin: 12px 0 18px;
}
#tk-wan-dashboard .wan-grid {
    display: grid;
    grid-template-columns: repeat(3,minmax(0,1fr));
    gap: 10px;
}
#tk-wan-dashboard .wan-box {
    padding: 10px 14px;
    border-radius: 9px;
    color: #fff;
    min-width: 0;
    border: 2px solid transparent;
}
#tk-wan-dashboard .wan-primary {
    background: #15803d;
}
#tk-wan-dashboard .wan-backup {
    background: #1d4ed8;
}
#tk-wan-dashboard .wan-lte {
    background: #b45309;
}
#tk-wan-dashboard .wan-active {
    border-color: #fff;
    box-shadow: 0 0 8px #22c55e;
}
#tk-wan-dashboard .wan-offline {
    background: #991b1b;
}
#tk-wan-dashboard .wan-name {
    font-size: 16px;
    font-weight: 700;
}
#tk-wan-dashboard .wan-status {
    font-size: 12px;
    margin: 5px 0;
}
#tk-wan-dashboard button {
    width: 100%;
    padding: 7px;
    margin-top: 4px;
    border: 0;
    border-radius: 5px;
    background: #fff;
    color: #111827;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}
#tk-wan-dashboard button:disabled {
    opacity: .4;
    cursor: not-allowed;
}
#tk-wan-dashboard .wan-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    font-size: 13px;
    font-weight: 700;
}
@media(max-width:600px) {
    #tk-wan-dashboard .wan-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<section id="tk-wan-dashboard">
    <div class="wan-heading">
        <span>WAN-Leitungsauswahl</span>
        <span id="tk-wan-mode">Wird geladen ...</span>
    </div>
    <div id="tk-wan-grid" class="wan-grid"></div>
    <div id="tk-wan-error" style="color:#ef4444;font-size:12px;margin-top:5px"></div>
</section>

<script>
(function () {
    const grid = document.getElementById('tk-wan-grid');
    const mode = document.getElementById('tk-wan-mode');
    const error = document.getElementById('tk-wan-error');

    const config = {
        PRIMARY: ['WAN 1', 'Hauptleitung', 'wan-primary'],
        BACKUP: ['WAN 2', 'Backup', 'wan-backup'],
        LTE: ['WAN 3', 'LTE', 'wan-lte']
    };

    let busy = false;
    let loading = false;

    async function refresh() {
        if (busy || loading) return;
        loading = true;

        try {
            const response = await fetch(
                '/api/triplewan.php?t=' + Date.now(),
                {cache:'no-store'}
            );

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const data = await response.json();
            if (busy) return;

            const locked = data.manual !== 'AUTO';

            mode.textContent = locked
                ? 'MANUELL: ' + (config[data.manual]?.[0] || data.manual)
                : 'AUTOMATIK AKTIV';

            const fragment = document.createDocumentFragment();

            for (const role of ['PRIMARY','BACKUP','LTE']) {
                const wan = data.interfaces.find(w => w.role === role);
                if (!wan) continue;

                const cfg = config[role];
                const selected = data.manual === role;

                const box = document.createElement('div');
                box.className = 'wan-box ' + cfg[2];

                if (wan.active) box.classList.add('wan-active');
                if (!wan.link) box.classList.add('wan-offline');

                const name = document.createElement('div');
                name.className = 'wan-name';
                name.textContent = cfg[0] + ' - ' + cfg[1];

                const status = document.createElement('div');
                status.className = 'wan-status';

                status.textContent = !wan.link
                    ? 'KEIN LINK'
                    : selected
                        ? (wan.active ? 'MANUELL AKTIV' : 'UMSCHALTUNG AUSSTEHEND')
                        : wan.active
                            ? 'AKTIVE LEITUNG'
                            : 'BEREITSCHAFT';

                const button = document.createElement('button');

                button.textContent = selected
                    ? 'Deaktivieren'
                    : cfg[0] + ' aktivieren';

                button.disabled = locked && !selected;

                button.onclick = async function () {
                    if (busy) return;

                    busy = true;
                    error.textContent = '';

                    grid.querySelectorAll('button').forEach(
                        b => b.disabled = true
                    );

                    try {
                        const result = await fetch(
                            '/api/wan-control.php',
                            {
                                method:'POST',
                                headers:{
                                    'Content-Type':'application/json'
                                },
                                body:JSON.stringify({
                                    mode:selected ? 'AUTO' : role
                                })
                            }
                        );

                        if (!result.ok) {
                            throw new Error('HTTP ' + result.status);
                        }

                        const reply = await result.json();

                        if (!reply.ok) {
                            throw new Error('Steuerung fehlgeschlagen');
                        }

                    } catch(e) {
                        error.textContent = 'Umschaltung nicht bestaetigt: ' + e.message;
                    } finally {
                        busy = false;
                        refresh();
                    }
                };

                box.append(name,status,button);
                fragment.appendChild(box);
            }

            grid.replaceChildren(fragment);

        } catch(e) {
            mode.textContent = 'STATUS NICHT VERFUEGBAR';
            error.textContent = e.message;
            grid.querySelectorAll('button').forEach(
                b => b.disabled = true
            );
        } finally {
            loading = false;
        }
    }

    refresh();
    setInterval(refresh,5000);
})();
</script>
