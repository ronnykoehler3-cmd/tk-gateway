async function loadSettings()
{
    const settings=
        await(
            await fetch(
                '/api/settings.php'
            )
        ).json();

    document.getElementById(
        'settings'
    ).innerHTML=`
    <div class="card">

        <label>
            <input
                type="checkbox"
                id="failover"
                ${settings.failover_enabled == 1 ? 'checked':''}
            >
            Automatisches Failover
        </label>

        <br><br>

        <label>
            <input
                type="checkbox"
                id="healthcheck"
                ${settings.healthcheck_enabled == 1 ? 'checked':''}
            >
            Healthcheck aktiv
        </label>

        <br><br>

        <label>
            <input
                type="checkbox"
                id="failback"
                ${settings.failback_enabled == 1 ? 'checked':''}
            >
            Automatisches Failback
        </label>

        <br><br>

        Intervall Sekunden

        <br><br>

        <input
            id="interval"
            value="${settings.healthcheck_interval}"
        >

        <br><br>

        Prüfziel

        <br><br>

        <input
            id="target"
            value="${settings.healthcheck_target}"
        >

        <br><br>

        <button
            onclick="saveSettings()"
            class="action-button"
        >
            Speichern
        </button>

    </div>
    `;
}

async function saveSettings()
{
    const data={
        failover_enabled:
            document.getElementById(
                'failover'
            ).checked ? 1:0,

        healthcheck_enabled:
            document.getElementById(
                'healthcheck'
            ).checked ? 1:0,

        failback_enabled:
            document.getElementById(
                'failback'
            ).checked ? 1:0,

        healthcheck_interval:
            document.getElementById(
                'interval'
            ).value,

        healthcheck_target:
            document.getElementById(
                'target'
            ).value
    };

    await fetch(
        '/api/save_settings.php',
        {
            method:'POST',
            headers:{
                'Content-Type':
                'application/json'
            },
            body:JSON.stringify(
                data
            )
        }
    );

    alert(
        'Einstellungen gespeichert'
    );
}

loadSettings();
