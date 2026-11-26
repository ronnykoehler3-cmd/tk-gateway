<?php
declare(strict_types=1);
include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/syslog.css">
<main class="syslog">
  <header class="syslog-heading"><div><h1>Syslog &amp; Fehlerdiagnose</h1><p>Telefonie und Gateway-Meldungen verständlich erklärt. Aktualisierung alle fünf Sekunden.</p></div>
  </header>
  <div class="syslog-toolbar card">
    <label>Suche <input id="search" type="search" placeholder="Gerät, MAC oder Meldung"></label>
    <label>Gerät / Quelle <select id="source"><option value="all">Alle Geräte</option><option value="fritzbox">FRITZ!Box / Telefonie</option><option value="vpn">VPN / Watchdog</option><option value="wan">WAN / Triple-WAN</option></select></label>
    <label>Zeitraum <select id="period"><option value="1">Letzte Stunde</option><option value="24" selected>Letzte 24 Stunden</option><option value="168">Letzte 7 Tage</option><option value="0">Alle verfügbaren (bis 2.000 je Quelle)</option></select></label>
    <label>Schweregrad <select id="severity"><option value="all">Alle</option><option value="error">Fehler</option><option value="warning">Warnungen</option><option value="info">Informationen</option></select></label>
    <label class="mode-toggle"><input id="expert" type="checkbox"> Expertenmodus</label>
  </div>
  <div class="syslog-actions"><span id="updated">Wird geladen …</span><button id="pause" class="secondary" type="button">Live-Protokoll pausieren</button><a id="export" class="button" href="/api/syslog.php?export=csv">Protokoll exportieren</a></div>
  <section class="syslog-counters" id="counters" aria-live="polite"></section>
  <section class="card"><h2>Gerätestatus</h2><div id="devices" class="device-grid"><p>Lade Gerätestatus …</p></div></section>
  <section class="card"><h2>Wiederkehrende Meldungen</h2><div id="repeated"><p>Keine Wiederholungen erkannt.</p></div></section>
  <section class="card"><h2>Letzte Meldungen</h2><div id="entries" class="entries"><p>Lade Protokoll …</p></div></section>
</main>
<script src="/assets/js/syslog.js" defer></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
