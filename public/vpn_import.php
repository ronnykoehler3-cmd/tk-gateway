<?php
include 'includes/header.php';
?>

<h1>VPN Profil importieren</h1>

<div class="card">

<form
    id="uploadForm"
    enctype="multipart/form-data">

    <label>Profilname</label>

    <br><br>

    <input
        type="text"
        id="name"
        name="name"
        required
        style="
            width:100%;
            padding:12px;
            border-radius:8px;
            border:none;
            margin-bottom:20px;
        ">

    <label>Datei importieren</label>

    <br><br>

    <input
        type="file"
        id="profile"
        name="profile"
        accept=".json,.conf,.ovpn,.vpn">

    <br><br>

    <label>VLESS / XRay Link</label>

    <br><br>

    <textarea
        id="vless_link"
        name="vless_link"
        rows="6"
        style="
            width:100%;
            padding:12px;
            border-radius:8px;
            border:none;
            margin-bottom:20px;
        "
        placeholder="vless://...."></textarea>

    <button
        type="submit"
        class="action-button">

        Profil importieren

    </button>

</form>

</div>

<div id="upload-result"></div>

<script src="/assets/js/vpn_import.js?v=<?php echo time(); ?>"></script>

<?php
include 'includes/footer.php';
?>
