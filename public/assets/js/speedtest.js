async function runSpeedtest()
{
    document.getElementById('speedtest-container').innerHTML = `
        <div class="card">
            <div class="card-title">
                Speedtest läuft...
            </div>

            <div class="card-value">
                Bitte warten (ca. 30-60 Sekunden)
            </div>
        </div>
    `;

    const response =
        await fetch('/api/speedtest.php?t=' + Date.now());

    const data =
        await response.json();

    if(!data.success)
    {
        document.getElementById('speedtest-container').innerHTML = `
            <div class="card">
                Fehler beim Speedtest.
            </div>
        `;
        return;
    }

    let stars = '★★★★★';

    if(data.latency > 80)
        stars = '★★★★';

    if(data.latency > 120)
        stars = '★★★';

    if(data.latency > 180)
        stars = '★★';

    if(data.latency > 250)
        stars = '★';

    document.getElementById('speedtest-container').innerHTML = `
    <div class="cards">

        <div class="card">
            <div class="card-title">Server</div>
            <div class="card-value">
                ${data.server}
            </div>
        </div>

        <div class="card">
            <div class="card-title">ISP</div>
            <div class="card-value">
                ${data.isp}
            </div>
        </div>

        <div class="card">
            <div class="card-title">Download</div>
            <div class="card-value">
                ${data.download} Mbit/s
            </div>
        </div>

        <div class="card">
            <div class="card-title">Upload</div>
            <div class="card-value">
                ${data.upload} Mbit/s
            </div>
        </div>

        <div class="card">
            <div class="card-title">Latenz</div>
            <div class="card-value">
                ${data.latency} ms
            </div>
        </div>

        <div class="card">
            <div class="card-title">Paketverlust</div>
            <div class="card-value">
                ${data.packetloss} %
            </div>
        </div>

        <div class="card">
            <div class="card-title">VoIP Bewertung</div>
            <div class="card-value">
                ${stars}
            </div>
        </div>

        <div class="card">
            <button
                onclick="runSpeedtest()"
                class="action-button">

                Speedtest erneut starten
            </button>
        </div>

    </div>
    `;
}
