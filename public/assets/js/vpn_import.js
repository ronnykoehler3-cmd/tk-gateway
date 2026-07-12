document
.getElementById('uploadForm')
.addEventListener(
'submit',
async function(e)
{
    e.preventDefault();

    const formData =
        new FormData();

    formData.append(
        'name',
        document.getElementById(
            'name'
        ).value
    );

    const fileInput =
        document.getElementById(
            'profile'
        );

    if(fileInput.files.length > 0)
    {
        formData.append(
            'profile',
            fileInput.files[0]
        );
    }

    formData.append(
        'vless_link',
        document.getElementById(
            'vless_link'
        ).value
    );

    const response =
        await fetch(
            '/api/upload_vpn_profile.php',
            {
                method:'POST',
                body:formData
            }
        );

    const result =
        await response.json();

    if(result.success)
    {
        document.getElementById(
            'upload-result'
        ).innerHTML =
        `
        <div class="card">

            Profil erfolgreich importiert.

            <br><br>

            Typ:
            <br>
            ${result.type}

            <br><br>

            Anbieter:
            <br>
            ${result.provider}

            <br><br>

            Server:
            <br>
            ${result.endpoint}

            <br><br>

            Verzeichnis:
            <br>
            ${result.directory}

        </div>
        `;
    }
    else
    {
        document.getElementById(
            'upload-result'
        ).innerHTML =
        `
        <div class="card">

            Fehler:

            <br><br>

            ${result.message}

        </div>
        `;
    }
});
