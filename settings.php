<?php

require __DIR__ . '/settings-lib.php';
$config = require __DIR__ . '/config.php';
$settings = loadSettings();
$message = '';
$dates = range(1, 31);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newSettings = getDefaultSettings();
    $newSettings['timezone'] = isset($_POST['timezone']) ? trim($_POST['timezone']) : 'Asia/Jakarta';
    $newSettings['schedule_enabled'] = isset($_POST['schedule_enabled']);
    $newSettings['telegram_enabled'] = isset($_POST['telegram_enabled']);
    $newSettings['auto_restart_app'] = isset($_POST['auto_restart_app']);
    $newSettings['auto_refresh_page'] = isset($_POST['auto_refresh_page']);
    $newSettings['auto_restart_minutes'] = max(1, (int)$_POST['auto_restart_minutes']);
    $newSettings['poll_interval_seconds'] = max(1, (int)$_POST['poll_interval_seconds']);

    foreach ($dates as $date) {
        $key = (string)$date;
        $newSettings['schedule'][$key] = array(
            'enabled' => isset($_POST['schedule_enabled_date'][$key]),
            'start' => isset($_POST['schedule_start'][$key]) ? $_POST['schedule_start'][$key] : '00:00',
            'end' => isset($_POST['schedule_end'][$key]) ? $_POST['schedule_end'][$key] : '23:59'
        );
    }

    foreach ($config['mysql'] as $key => $dbConfig) {
        $newSettings['database_activity']['mysql'][$key] = isset($_POST['database_activity']['mysql'][$key]);
    }
    foreach ($config['postgresql'] as $key => $dbConfig) {
        $newSettings['database_activity']['postgresql'][$key] = isset($_POST['database_activity']['postgresql'][$key]);
    }

    if (saveSettings($newSettings)) {
        $settings = $newSettings;
        $message = 'Pengaturan berhasil disimpan.';
    } else {
        $message = 'Pengaturan gagal disimpan. Pastikan settings.json dapat ditulis oleh web server.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Pengaturan Auto Pantau</title>
  <style>
    :root { --primary: #2563eb; --navy: #0f172a; --muted: #64748b; --border: #e2e8f0; --surface: #fff; --bg: #f1f5f9; }
    * { box-sizing: border-box; }
    body { font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: linear-gradient(135deg, #eff6ff 0%, var(--bg) 50%, #f8fafc 100%); margin: 0; color: var(--navy); min-height: 100vh; }
    .page { max-width: 1050px; margin: 0 auto; padding: 32px 22px 50px; }
    .header { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 24px; }
    .header h1 { margin: 0; font-size: 28px; letter-spacing: -.5px; }
    .card { background: rgba(255,255,255,.95); border: 1px solid var(--border); border-radius: 14px; padding: 24px; margin: 18px 0; box-shadow: 0 8px 25px rgba(15,23,42,.07); }
    h2 { margin: 0 0 18px; font-size: 18px; } h3 { color: #475569; margin: 20px 0 10px; font-size: 14px; text-transform: uppercase; letter-spacing: .05em; }
    .row { display: flex; gap: 20px; flex-wrap: wrap; margin-top: 18px; }
    .field { flex: 1; min-width: 220px; margin-bottom: 12px; }
    label { display: block; margin-bottom: 7px; font-weight: 600; color: #334155; }
    input[type="text"], input[type="number"], input[type="time"] { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; color: var(--navy); background: #fff; outline: none; transition: .2s; }
    input[type="text"]:focus, input[type="number"]:focus, input[type="time"]:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
    input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer; }
    table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 12px; }
    th { background: #f8fafc; color: #475569; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
    th, td { border-right: 1px solid var(--border); border-bottom: 1px solid var(--border); padding: 11px; text-align: left; }
    th:first-child, td:first-child { border-left: 1px solid var(--border); } tr:first-child th { border-top: 1px solid var(--border); }
    tr:first-child th:first-child { border-top-left-radius: 9px; } tr:first-child th:last-child { border-top-right-radius: 9px; }
    tbody tr:hover td { background: #f8fafc; }
    .switch { display: flex; gap: 10px; align-items: center; padding: 11px 13px; margin: 8px 0; border: 1px solid var(--border); border-radius: 9px; background: #f8fafc; }
    .switch label { margin: 0; cursor: pointer; font-weight: 500; flex: 1; }
    .message { padding: 13px 16px; background: #dcfce7; color: #166534; border: 1px solid #86efac; border-radius: 9px; font-weight: 600; }
    .button { padding: 11px 18px; border: 0; border-radius: 9px; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 700; transition: .2s; }
    .button:hover { transform: translateY(-1px); }
    .primary { background: var(--primary); color: white; box-shadow: 0 5px 14px rgba(37,99,235,.22); }
    .primary:hover { background: #1d4ed8; }
    .secondary { background: #fff; color: #334155; border: 1px solid #cbd5e1; }
    .form-actions { position: sticky; bottom: 0; padding: 14px 0; background: linear-gradient(transparent, var(--bg) 35%); text-align: right; }
    @media (max-width: 650px) { .page { padding: 20px 12px 40px; } .header { align-items: flex-start; flex-direction: column; } .card { padding: 17px; } table { min-width: 570px; } .card:has(table) { overflow-x: auto; } }
  </style>
</head>
<body>
<div class="page">
  <div class="header">
    <h1>Pengaturan Auto Pantau</h1>
    <a class="button secondary" href="index.php">Kembali ke Dashboard</a>
  </div>

  <?php if ($message !== ''): ?><div class="message"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

  <form method="post">
    <div class="card">
      <h2>Pengaturan Umum</h2>
      <div class="switch"><input type="checkbox" id="telegram_enabled" name="telegram_enabled" <?php echo $settings['telegram_enabled'] ? 'checked' : ''; ?>><label for="telegram_enabled">Aktifkan notifikasi Telegram</label></div>
      <div class="switch"><input type="checkbox" id="auto_restart_app" name="auto_restart_app" <?php echo $settings['auto_restart_app'] ? 'checked' : ''; ?>><label for="auto_restart_app">Aktifkan auto restart server APP saat threshold tercapai</label></div>
      <div class="switch"><input type="checkbox" id="auto_refresh_page" name="auto_refresh_page" <?php echo $settings['auto_refresh_page'] ? 'checked' : ''; ?>><label for="auto_refresh_page">Aktifkan auto refresh halaman dashboard</label></div>
      <div class="row">
        <div class="field"><label>Auto refresh setiap (menit)</label><input type="number" min="1" name="auto_restart_minutes" value="<?php echo (int)$settings['auto_restart_minutes']; ?>"></div>
        <div class="field"><label>Polling database setiap (detik)</label><input type="number" min="1" name="poll_interval_seconds" value="<?php echo (int)$settings['poll_interval_seconds']; ?>"></div>
      </div>
    </div>

    <div class="card">
      <h2>Aktivitas Koneksi Database</h2>
      <?php foreach (array('mysql' => 'MySQL', 'postgresql' => 'PostgreSQL') as $engine => $engineLabel): ?>
        <h3><?php echo $engineLabel; ?></h3>
        <?php foreach ($config[$engine] as $key => $dbConfig): ?>
          <div class="switch">
            <input type="checkbox" id="db-<?php echo $engine . '-' . $key; ?>" name="database_activity[<?php echo $engine; ?>][<?php echo $key; ?>]" <?php echo !empty($settings['database_activity'][$engine][$key]) ? 'checked' : ''; ?>>
            <label for="db-<?php echo $engine . '-' . $key; ?>">DB <?php echo htmlspecialchars($dbConfig['label']); ?></label>
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>

    <div class="card">
      <h2>Jadwal Aktif Berdasarkan Tanggal</h2>
      <div class="switch"><input type="checkbox" id="schedule_enabled" name="schedule_enabled" <?php echo $settings['schedule_enabled'] ? 'checked' : ''; ?>><label for="schedule_enabled">Gunakan pembatasan jadwal</label></div>
      <div class="field"><label>Zona waktu</label><input type="text" name="timezone" value="<?php echo htmlspecialchars($settings['timezone']); ?>"></div>
      <p style="color:#64748b">Tanggal 1–31 selalu tersedia. Pada bulan yang lebih pendek, tanggal yang tidak ada otomatis tidak pernah digunakan.</p>
      <table>
        <thead><tr><th>Tanggal</th><th>Aktif</th><th>Mulai</th><th>Selesai</th></tr></thead>
        <tbody>
        <?php foreach ($dates as $date): $key = (string)$date; $day = $settings['schedule'][$key]; ?>
          <tr>
            <td>Tanggal <?php echo $date; ?></td>
            <td><input type="checkbox" name="schedule_enabled_date[<?php echo $key; ?>]" <?php echo $day['enabled'] ? 'checked' : ''; ?>></td>
            <td><input type="time" name="schedule_start[<?php echo $key; ?>]" value="<?php echo htmlspecialchars($day['start']); ?>"></td>
            <td><input type="time" name="schedule_end[<?php echo $key; ?>]" value="<?php echo htmlspecialchars($day['end']); ?>"></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="form-actions"><button class="button primary" type="submit">Simpan Pengaturan</button></div>
  </form>
</div>
</body>
</html>
