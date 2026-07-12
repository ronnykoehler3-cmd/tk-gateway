async function loadProfiles()
{
    const response =
        await fetch(
            '/api/vpn_profiles.php?t=' +
            Date.now()
        );

    const profiles =
        await response.json();

    let html =
    `
    <div class="cards">
    `;

    profiles.forEach(function(profile)
    {
        let status='Inaktiv';
        let color='#ef4444';
        let border='';

        if(profile.active == 1)
        {
            status='Aktiv';
            color='#22c55e';
            border='border:2px solid #22c55e;';
        }

        html +=
        `
        <div class="card" style="${border}">

            <div class="card-title">
                ${profile.name}
            </div>

            <div style="
                color:${color};
                font-size:28px;
                font-weight:bold;
                margin-bottom:20px;
            ">
                ${status}
            </div>

            <strong>Typ:</strong><br>
            ${profile.type ?? 'Unbekannt'}

            <br><br>

            <strong>Anbieter:</strong><br>
            ${profile.provider ?? 'Unbekannt'}

            <br><br>

            <strong>Server:</strong><br>
            ${profile.endpoint ?? '-'}

            <br><br>

            <strong>Priorität:</strong><br>
            ${profile.priority ?? '-'}

            <br><br>

            <button
                onclick="activateProfile(${profile.id})"
                class="action-button">

                Profil aktivieren

            </button>

            <br><br>

            <button
                onclick="deleteProfile(${profile.id})"
                class="action-button danger-button">

                Profil löschen

            </button>

        </div>
        `;
    });

    html +=
    `
    </div>
    `;

    document.getElementById(
        'vpn-profiles'
    ).innerHTML = html;
}

async function activateProfile(id)
{
    if(
        !confirm(
            'VPN Profil wirklich aktivieren?'
        )
    )
    {
        return;
    }

    const response =
        await fetch(
            '/api/activate_vpn_profile.php?id=' +
            id
        );

    const result =
        await response.json();

    if(result.success)
    {
        alert(
            'VPN Profil wurde aktiviert.'
        );

        loadProfiles();
    }
    else
    {
        alert(
            result.message ??
            'Fehler beim Aktivieren.'
        );
    }
}

async function deleteProfile(id)
{
    if(
        !confirm(
            'VPN Profil wirklich löschen?'
        )
    )
    {
        return;
    }

    const response =
        await fetch(
            '/api/delete_vpn_profile.php?id=' +
            id
        );

    const result =
        await response.json();

    if(result.success)
    {
        alert(
            'VPN Profil gelöscht.'
        );

        loadProfiles();
    }
    else
    {
        alert(
            result.message
        );
    }
}

loadProfiles();

setInterval(
    loadProfiles,
    10000
);
