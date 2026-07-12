async function loadEvents()
{
    const response =
        await fetch(
            '/api/events.php?t=' +
            Date.now()
        );

    const events =
        await response.json();

    let html='';

    events.forEach(function(event)
    {
        let color='#22c55e';

        if(event.level === 'WARNING')
            color='#f59e0b';

        if(event.level === 'ERROR')
            color='#ef4444';

        html += `
            <div class="card">

                <div style="
                    color:${color};
                    font-weight:bold;
                    margin-bottom:10px;
                ">
                    ${event.level}
                </div>

                <div style="
                    color:#999;
                    margin-bottom:10px;
                ">
                    ${event.timestamp}
                </div>

                <div>
                    ${event.message}
                </div>

            </div>
        `;
    });

    document.getElementById(
        'events'
    ).innerHTML = html;
}

loadEvents();

setInterval(
    loadEvents,
    10000
);
