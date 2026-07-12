<?php
include 'includes/header.php';
?>

<h1>Internet Speedtest</h1>

<div id="speedtest-container">

    <div class="card">
        <div class="card-title">
            Speedtest bereit
        </div>

        <button
            onclick="runSpeedtest()"
            class="action-button">

            VPN Speedtest starten 🇩🇪
        </button>
    </div>

</div>

<script src="/assets/js/speedtest.js?v=<?php echo time(); ?>"></script>

<?php
include 'includes/footer.php';
?>
