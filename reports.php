<?php

require __DIR__ . '/report-lib.php';

$availableDates = getAvailableReportDates();
$selectedDate = isset($_GET['date']) ? $_GET['date'] : (!empty($availableDates) ? $availableDates[0] : date('Y-m-d'));
$selectedType = isset($_GET['type']) ? $_GET['type'] : 'all';
$reports = loadReportsByDate($selectedDate);
$filteredReports = array();

foreach ($reports as $report) {
    if ($selectedType === 'all' || (isset($report['type']) && $report['type'] === $selectedType)) {
        $filteredReports[] = $report;
    }
}
$filteredReports = array_reverse($filteredReports);

$typeLabels = array(
    'all' => 'Semua Aktivitas',
    'app_restart' => 'Restart APP',
    'mysql_query' => 'Kill Query MySQL',
    'postgresql_query' => 'Cancel Query PostgreSQL'
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Laporan Auto Pantau</title>
  <style>
    :root { --primary:#2563eb; --navy:#0f172a; --muted:#64748b; --border:#e2e8f0; --bg:#f1f5f9; }
    * { box-sizing:border-box; }
    body { margin:0; min-height:100vh; font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; color:var(--navy); background:linear-gradient(135deg,#eff6ff,var(--bg)); }
    .page { max-width:1200px; margin:auto; padding:30px 20px; }
    .header { display:flex; justify-content:space-between; align-items:center; gap:15px; margin-bottom:22px; }
    h1 { margin:0 0 6px; } .subtitle { margin:0; color:var(--muted); }
    .button { display:inline-block; padding:10px 16px; border-radius:9px; text-decoration:none; font-weight:700; background:#fff; color:#334155; border:1px solid #cbd5e1; }
    .card { background:#fff; border:1px solid var(--border); border-radius:14px; padding:20px; margin-bottom:18px; box-shadow:0 8px 25px rgba(15,23,42,.07); }
    .filters { display:grid; grid-template-columns:1fr 1fr auto; gap:14px; align-items:end; }
    label { display:block; margin-bottom:6px; font-weight:700; color:#334155; }
    select { width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; background:#fff; }
    button { padding:11px 18px; border:0; border-radius:8px; background:var(--primary); color:#fff; font-weight:700; cursor:pointer; }
    .report { border-left:4px solid var(--primary); }
    .report.failed { border-left-color:#dc2626; }
    .meta { display:flex; flex-wrap:wrap; gap:8px 18px; color:var(--muted); font-size:13px; margin:8px 0 14px; }
    .badge { display:inline-block; padding:4px 9px; border-radius:999px; background:#dbeafe; color:#1d4ed8; font-size:12px; font-weight:800; }
    .failed .badge { background:#fee2e2; color:#991b1b; }
    pre { margin:8px 0 0; padding:14px; overflow:auto; white-space:pre-wrap; word-break:break-word; border-radius:9px; background:#0f172a; color:#e2e8f0; font-size:12px; line-height:1.55; }
    .empty { text-align:center; color:var(--muted); padding:45px; }
    @media(max-width:700px) { .header { align-items:flex-start; flex-direction:column; } .filters { grid-template-columns:1fr; } }
  </style>
</head>
<body>
<div class="page">
  <div class="header">
    <div><h1>Laporan Auto Pantau</h1><p class="subtitle">Riwayat aktivitas tersimpan selama tujuh hari terakhir.</p></div>
    <a class="button" href="index.php">Kembali ke Dashboard</a>
  </div>

  <div class="card">
    <form method="get" class="filters">
      <div><label>Tanggal laporan</label><select name="date">
        <?php if (empty($availableDates)): ?><option value="<?php echo htmlspecialchars($selectedDate); ?>"><?php echo htmlspecialchars($selectedDate); ?></option><?php endif; ?>
        <?php foreach ($availableDates as $date): ?><option value="<?php echo $date; ?>" <?php echo $date === $selectedDate ? 'selected' : ''; ?>><?php echo $date; ?></option><?php endforeach; ?>
      </select></div>
      <div><label>Jenis aktivitas</label><select name="type">
        <?php foreach ($typeLabels as $type => $label): ?><option value="<?php echo $type; ?>" <?php echo $type === $selectedType ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?>
      </select></div>
      <button type="submit">Terapkan Filter</button>
    </form>
  </div>

  <?php if (empty($filteredReports)): ?>
    <div class="card empty">Belum ada laporan untuk tanggal dan filter ini.</div>
  <?php else: ?>
    <?php foreach ($filteredReports as $report): ?>
      <div class="card report <?php echo isset($report['status']) && $report['status'] === 'failed' ? 'failed' : ''; ?>">
        <span class="badge"><?php echo htmlspecialchars(isset($typeLabels[$report['type']]) ? $typeLabels[$report['type']] : $report['type']); ?></span>
        <h2><?php echo htmlspecialchars($report['action']); ?> — <?php echo htmlspecialchars($report['server']); ?></h2>
        <div class="meta">
          <span>Waktu: <strong><?php echo htmlspecialchars($report['timestamp']); ?></strong></span>
          <span>Status: <strong><?php echo htmlspecialchars($report['status']); ?></strong></span>
          <?php if (isset($report['process_id'])): ?><span>PID/ID: <strong><?php echo (int)$report['process_id']; ?></strong></span><?php endif; ?>
          <?php if (isset($report['user'])): ?><span>User: <strong><?php echo htmlspecialchars($report['user']); ?></strong></span><?php endif; ?>
          <?php if (isset($report['duration_seconds'])): ?><span>Durasi: <strong><?php echo htmlspecialchars((string)$report['duration_seconds']); ?>s</strong></span><?php endif; ?>
          <?php if (isset($report['active_requests'])): ?><span>Request aktif: <strong><?php echo $report['active_requests'] === null ? 'UNKNOWN' : (int)$report['active_requests']; ?></strong></span><?php endif; ?>
          <?php if (isset($report['threshold'])): ?><span>Threshold: <strong><?php echo (int)$report['threshold']; ?></strong></span><?php endif; ?>
        </div>
        <?php if (!empty($report['query'])): ?><label>Query</label><pre><?php echo htmlspecialchars($report['query'], ENT_QUOTES, 'UTF-8'); ?></pre><?php endif; ?>
        <?php if (!empty($report['error'])): ?><label>Error</label><pre><?php echo htmlspecialchars($report['error'], ENT_QUOTES, 'UTF-8'); ?></pre><?php endif; ?>
        <?php if (!empty($report['details'])): ?><p><?php echo htmlspecialchars($report['details']); ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
</body>
</html>
