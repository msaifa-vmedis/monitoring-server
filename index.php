<?php

require __DIR__ . '/settings-lib.php';

$settings = loadSettings();
$jagaServer = isOnDutySettings($settings);
$statusJaga = $jagaServer ? 'YA' : 'TIDAK';
$config = require __DIR__ . '/config.php';
$monitors = array();

foreach ($config['mysql'] as $serverKey => $dbConfig) {
    if (isDatabaseActivityEnabled($settings, 'mysql', $serverKey)) {
        $monitors[] = array(
            'id' => 'mysql-' . $serverKey,
            'label' => $dbConfig['label'],
            'url' => 'fetch-mysql.php?server=' . rawurlencode($serverKey)
        );
    }
}

foreach ($config['postgresql'] as $serverKey => $dbConfig) {
    if (isDatabaseActivityEnabled($settings, 'postgresql', $serverKey)) {
        $monitors[] = array(
            'id' => 'postgresql-' . $serverKey,
            'label' => $dbConfig['label'],
            'url' => 'fetch-postgresql.php?server=' . rawurlencode($serverKey)
        );
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Database Monitoring</title>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    :root { --primary: #2563eb; --navy: #0f172a; --muted: #64748b; --border: #e2e8f0; --surface: #fff; --bg: #f1f5f9; --success: #16a34a; --danger: #dc2626; }
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: var(--navy); background: linear-gradient(135deg, #eff6ff 0%, var(--bg) 45%, #f8fafc 100%); }
    .page { max-width: 1600px; margin: 0 auto; padding: 28px; }
    .topbar { display: flex; justify-content: space-between; align-items: center; gap: 20px; margin-bottom: 24px; }
    .title-wrap h1 { margin: 0 0 7px; font-size: 28px; letter-spacing: -.5px; }
    .title-wrap p { margin: 0; color: var(--muted); }
    .actions { display: flex; align-items: center; gap: 12px; }
    .status { display: inline-flex; align-items: center; gap: 8px; padding: 9px 13px; border-radius: 999px; font-weight: 700; background: <?php echo $jagaServer ? '#dcfce7' : '#fee2e2'; ?>; color: <?php echo $jagaServer ? '#166534' : '#991b1b'; ?>; }
    .status-dot { width: 9px; height: 9px; border-radius: 50%; background: <?php echo $jagaServer ? '#22c55e' : '#ef4444'; ?>; box-shadow: 0 0 0 4px <?php echo $jagaServer ? '#bbf7d0' : '#fecaca'; ?>; }
    .button { color: #fff; background: var(--primary); text-decoration: none; font-weight: 700; padding: 10px 16px; border-radius: 9px; box-shadow: 0 4px 12px rgba(37,99,235,.22); transition: .2s; }
    .button:hover { transform: translateY(-1px); background: #1d4ed8; }
    .container { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
    .box { min-width: 0; overflow: hidden; background: rgba(255,255,255,.92); border: 1px solid rgba(226,232,240,.9); border-radius: 14px; box-shadow: 0 8px 25px rgba(15,23,42,.07); }
    .box h2 { margin: 0; padding: 16px 20px; font-size: 16px; border-bottom: 1px solid var(--border); background: linear-gradient(90deg, #f8fafc, #fff); }
    .box h2:before { content: ""; display: inline-block; width: 8px; height: 8px; margin-right: 9px; border-radius: 50%; background: var(--primary); }
    .table-container { width: 100%; overflow-x: auto; padding: 12px; min-height: 95px; color: var(--muted); }
    table { width: 100%; border-collapse: separate; border-spacing: 0; color: #334155; font-size: 12px; }
    th { background: #f8fafc; color: #475569; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
    th, td { border-right: 1px solid var(--border); border-bottom: 1px solid var(--border); padding: 10px; text-align: left; vertical-align: top; }
    th:first-child, td:first-child { border-left: 1px solid var(--border); }
    tr:first-child th { border-top: 1px solid var(--border); }
    tr:first-child th:first-child { border-top-left-radius: 8px; }
    tr:first-child th:last-child { border-top-right-radius: 8px; }
    tr:hover td { background: #f8fafc; }
    .app-box { grid-column: span 2; }
    .app-box .table-container { overflow: visible; }
    .app-box .active-request-value { font-size: 24px; font-weight: 800; color: var(--primary); line-height: 1; letter-spacing: -.4px; }
    @media (max-width: 900px) { .container { grid-template-columns: 1fr; } .app-box { grid-column: span 1; } }
    @media (max-width: 640px) { .page { padding: 18px 12px; } .topbar { align-items: flex-start; flex-direction: column; } .actions { width: 100%; justify-content: space-between; } .title-wrap h1 { font-size: 23px; } }
  </style>
</head>
<body>
<div class="page">
  <div class="topbar">
    <div class="title-wrap">
      <h1>Database Monitoring</h1>
      <p>Pantau koneksi dan query database secara real-time.</p>
    </div>
    <div class="actions">
      <span class="status"><span class="status-dot"></span>Jaga <?php echo htmlspecialchars($statusJaga); ?></span>
      <a class="button" href="reports.php">Laporan</a>
      <a class="button" href="settings.php">Pengaturan</a>
    </div>
  </div>
  <div class="container">
    <?php foreach ($monitors as $monitor): ?>
      <div class="box">
        <h2>DB <?php echo htmlspecialchars($monitor['label']); ?></h2>
        <div class="table-container" id="<?php echo htmlspecialchars($monitor['id']); ?>"></div>
      </div>
    <?php endforeach; ?>

    <div class="box app-box">
      <h2>APP MONITOR</h2>
      <div class="table-container" id="app-monitor"></div>
    </div>
  </div>
</div>

<script>
  var isOnDuty = <?php echo json_encode($statusJaga); ?>;
  var monitors = <?php echo json_encode($monitors); ?>;
  var autoRefreshPage = <?php echo !empty($settings['auto_refresh_page']) ? 'true' : 'false'; ?>;
  var autoRestartMilliseconds = <?php echo (int)$settings['auto_restart_minutes'] * 60 * 1000; ?>;
  var pollMilliseconds = <?php echo (int)$settings['poll_interval_seconds'] * 1000; ?>;

  if (autoRefreshPage) {
    setInterval(function() {
      location.reload();
    }, autoRestartMilliseconds);
  }

  function loadTable(monitor) {
    $('#' + monitor.id).load(monitor.url);
  }

  function loadAppMonitor() {
    $('#app-monitor').load('fetch-app.php');
  }

  $(document).ready(function() {
    loadAppMonitor();
    setInterval(loadAppMonitor, pollMilliseconds);

    if (isOnDuty === 'TIDAK') return;

    $.each(monitors, function(index, monitor) {
      loadTable(monitor);
      setInterval(function() {
        loadTable(monitor);
      }, pollMilliseconds);
    });
  });
</script>
</body>
</html>
