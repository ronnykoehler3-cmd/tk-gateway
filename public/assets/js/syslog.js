(() => {
  'use strict';
  const root = document.querySelector('.syslog');
  if (!root) return;
  const $ = (id) => document.getElementById(id);
  const state = { paused: false, timer: null, busy: false };
  const esc = (value) => String(value ?? '—');
  const item = (tag, className, value) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (value !== undefined) node.textContent = value;
    return node;
  };

  function filters() {
    const params = new URLSearchParams({
      q: $('search').value,
      source: $('source').value,
      hours: $('period').value,
      severity: $('severity').value,
      expert: $('expert').checked ? '1' : '0',
      t: Date.now().toString()
    });
    $('export').href = '/api/syslog.php?' + new URLSearchParams({ ...Object.fromEntries(params), export: 'csv' });
    return params;
  }

  function render(data) {
    const counts = data.summary.counts;
    const counters = $('counters');
    counters.replaceChildren();
    [['Fehler', counts.error, 'error'], ['Warnungen', counts.warning, 'warning'], ['Informationen', counts.info, 'info'], ['Meldungen', data.summary.total, 'neutral']].forEach(([label, count, cls]) => {
      const card = item('div', 'counter ' + cls);
      card.append(item('strong', '', count), item('span', '', label));
      counters.append(card);
    });

    const devices = $('devices');
    devices.replaceChildren();
    if (!data.summary.devices.length) devices.append(item('p', 'muted', 'Für diesen Zeitraum liegen keine lesbaren Meldungen vor.'));
    data.summary.devices.forEach((device) => {
      const card = item('article', 'device-card ' + device.status);
      const status = device.status === 'error' ? 'Fehler im gewählten Zeitraum' : (device.status === 'warning' ? 'Warnungen im gewählten Zeitraum' : 'Keine bekannten Fehler im gewählten Zeitraum');
      card.append(item('h3', '', device.device), item('p', 'device-model', device.model || 'Modell nicht erkannt'));
      const stats = item('p', 'device-facts');
      stats.append(document.createTextNode('MAC: ' + esc(device.mac) + ' · ' + status));
      card.append(stats, item('p', '', 'Letzte Meldung: ' + esc(device.lastMessage)), item('p', '', 'Zuletzt gesehen: ' + new Date(device.lastSeen).toLocaleString('de-DE')), item('p', '', 'Im gewählten Zeitraum: ' + device.errors + ' Fehler · ' + device.warnings + ' Warnungen'));
      devices.append(card);
    });

    const repeated = $('repeated');
    repeated.replaceChildren();
    if (!data.summary.repeated.length) repeated.append(item('p', 'muted', 'Keine wiederkehrenden Meldungen im gewählten Zeitraum.'));
    data.summary.repeated.forEach((entry) => {
      const row = item('p', 'repeat-row');
      row.append(item('span', 'pill ' + entry.severity, entry.count + ' ×'), document.createTextNode(' ' + entry.message + ' · ' + entry.device));
      repeated.append(row);
    });

    const list = $('entries');
    list.replaceChildren();
    if (!data.entries.length) list.append(item('p', 'muted', 'Keine Meldungen passend zu den Filtern.'));
    data.entries.forEach((entry) => {
      const article = item('article', 'entry ' + entry.severity);
      const top = item('div', 'entry-top');
      top.append(item('span', 'pill ' + entry.severity, entry.severity === 'error' ? 'FEHLER' : entry.severity === 'warning' ? 'WARNUNG' : 'INFO'), item('time', '', new Date(entry.timestamp).toLocaleString('de-DE')));
      article.append(top, item('h3', '', entry.title), item('p', 'entry-category', entry.category));
      const identity = item('p', 'identity');
      identity.append(document.createTextNode('Tatsächliches Gerät: ' + esc(entry.device) + ' · Syslog-Absender-IP: ' + esc(entry.senderIp || 'nicht im Log enthalten') + ' · MAC: ' + esc(entry.mac)));
      article.append(identity, item('p', 'advice', entry.advice));
      if (entry.unknown) article.append(item('p', 'unknown', 'Unbekannte Meldung: noch keine feste Fehlerregel vorhanden.'));
      if ($('expert').checked) {
        article.append(item('details', '', '')); // use a real details element below to keep all content text-only
        const details = article.lastChild;
        details.append(item('summary', '', 'Technische Originalmeldung (Zugangsdaten bleiben verborgen)'), item('pre', 'raw-message', entry.message));
        article.append(item('small', 'source-line', 'Logquelle: ' + entry.sourceLabel + ' · Syslog-Hostname: ' + esc(entry.syslogHost)));
      }
      list.append(article);
    });
    $('updated').textContent = 'Stand: ' + new Date(data.updatedAt).toLocaleTimeString('de-DE') + (state.paused ? ' · pausiert' : ' · Live-Aktualisierung aktiv');
  }

  async function load() {
    if (state.paused || state.busy) return;
    state.busy = true;
    try {
      const response = await fetch('/api/syslog.php?' + filters(), { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } });
      if (!response.ok) throw new Error('HTTP ' + response.status);
      render(await response.json());
    } catch (error) {
      const status = /HTTP (\d+)/.exec(String(error?.message || ''));
      $('updated').textContent = status
        ? 'Syslog-API antwortet mit HTTP ' + status[1] + '. Logzugriff prüfen.'
        : 'Syslog-API ist nicht erreichbar. Verbindung prüfen.';
    } finally { state.busy = false; }
  }

  ['search', 'source', 'period', 'severity', 'expert'].forEach((id) => $(id).addEventListener(id === 'search' ? 'input' : 'change', load));
  $('pause').addEventListener('click', () => {
    state.paused = !state.paused;
    $('pause').textContent = state.paused ? 'Live-Protokoll fortsetzen' : 'Live-Protokoll pausieren';
    if (!state.paused) load();
    else $('updated').textContent = 'Live-Protokoll pausiert.';
  });
  load();
  state.timer = window.setInterval(load, 5000);
})();
