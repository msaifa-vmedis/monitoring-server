<?php

require __DIR__ . '/settings-lib.php';
require __DIR__ . '/telegram.php';
require __DIR__ . '/report-lib.php';

$settings = loadSettings();
$jagaServer = isOnDutySettings($settings);

$config = require __DIR__ . '/config.php';
$serverKey = isset($_GET['server']) ? $_GET['server'] : '';

if (!isset($config['postgresql'][$serverKey])) {
    die('Konfigurasi server PostgreSQL tidak ditemukan.');
}

if (!isDatabaseActivityEnabled($settings, 'postgresql', $serverKey)) {
    die('Aktivitas koneksi database ini dinonaktifkan.');
}

$dbConfig = $config['postgresql'][$serverKey];

$connString = sprintf(
    'host=%s port=%s dbname=%s user=%s password=%s connect_timeout=%s',
    $dbConfig['host'],
    $dbConfig['port'],
    $dbConfig['database'],
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['connect_timeout']
);
if (!extension_loaded('pgsql') || !function_exists('pg_connect')) {
    die('Ekstensi PHP PostgreSQL (pgsql) belum terpasang atau belum aktif.');
}

$conn = @pg_connect($connString);

if (!$conn) {
    $lastError = error_get_last();
    $errorMessage = isset($lastError['message'])
        ? $lastError['message']
        : 'Detail error tidak tersedia.';
    die('Koneksi PostgreSQL gagal: ' . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'));
}

$serverLabel = $dbConfig['label'];
$queryTimeout = 60;

$maxConnections = 0;
$connectionsUsed = 0;
$usagePercent = 0.0;

$maxConnResult = pg_query($conn, "SELECT current_setting('max_connections')::int AS max_connections");
if ($maxConnResult && ($maxConnRow = pg_fetch_assoc($maxConnResult))) {
    $maxConnections = (int)$maxConnRow['max_connections'];
}

$connectionsResult = pg_query($conn, "
    SELECT COUNT(*)::int AS connections_used
    FROM pg_stat_activity
    WHERE backend_type = 'client backend'
");
if ($connectionsResult && ($connectionsRow = pg_fetch_assoc($connectionsResult))) {
    $connectionsUsed = (int)$connectionsRow['connections_used'];
}

if ($maxConnections > 0) {
    $usagePercent = ($connectionsUsed / $maxConnections) * 100;
}

/*
 * pg_cancel_backend membatalkan query yang sedang berjalan, tetapi tidak
 * memutuskan koneksi client. PID koneksi monitor ini selalu dikecualikan.
 */
$query = "
    SELECT
        pid,
        usename,
        state,
        wait_event_type,
        wait_event,
        EXTRACT(EPOCH FROM (clock_timestamp() - query_start)) AS duration_seconds,
        clock_timestamp() - query_start AS duration,
        LEFT(query, 300) AS query
    FROM pg_stat_activity
    WHERE state = 'active'
      AND backend_type = 'client backend'
      AND pid <> pg_backend_pid()
      AND query_start IS NOT NULL
    ORDER BY query_start ASC
";
$result = pg_query($conn, $query);

if (!$result) {
    die('Query error: ' . htmlspecialchars(pg_last_error($conn)));
}

echo '<table border="1" cellpadding="10">';
echo '<tr><th>PID</th><th>USER</th><th>STATE</th><th>WAIT EVENT</th><th>DURATION</th><th>QUERY</th><th>ACTION</th></tr>';

$notifications = [];
$canceledCount = 0;
$failedCount = 0;

while ($row = pg_fetch_assoc($result)) {
    $action = 'MONITORING';
    $pid = (int)$row['pid'];
    $durationSeconds = (float)$row['duration_seconds'];
    $waitEvent = trim((string)$row['wait_event_type'] . ' / ' . (string)$row['wait_event'], ' /');

    // Semua query aktif ditampilkan, tetapi hanya query melewati timeout yang dibatalkan.
    if ($jagaServer && $durationSeconds > $queryTimeout) {
        $cancelResult = pg_query_params(
            $conn,
            'SELECT pg_cancel_backend($1::int) AS canceled',
            [$pid]
        );
        $cancelRow = $cancelResult ? pg_fetch_assoc($cancelResult) : false;
        $wasCanceled = $cancelRow && $cancelRow['canceled'] === 't';

        if ($wasCanceled) {
            $canceledCount++;
            $action = 'CANCELED';
            $fullQuery = (string)$row['query'];
            $queryText = $fullQuery !== '' ? substr($fullQuery, 0, 160) : 'NULL';
            $notifications[] = "CANCEL LONG QUERY DB {$serverLabel} (PID: {$pid}, Time: "
                . number_format($durationSeconds, 3, '.', '') . "s): {$queryText}";
            saveReport(array(
                'type' => 'postgresql_query',
                'action' => 'CANCEL QUERY',
                'status' => 'success',
                'server' => $serverLabel,
                'process_id' => $pid,
                'user' => (string)$row['usename'],
                'duration_seconds' => $durationSeconds,
                'query' => $fullQuery
            ));
        } else {
            $failedCount++;
            $action = 'FAILED';
            $error = $cancelResult ? 'backend tidak ditemukan atau tidak dapat dibatalkan' : pg_last_error($conn);
            $notifications[] = "Gagal cancel query DB {$serverLabel} PID {$pid}: {$error}";
            saveReport(array(
                'type' => 'postgresql_query',
                'action' => 'CANCEL QUERY',
                'status' => 'failed',
                'server' => $serverLabel,
                'process_id' => $pid,
                'user' => (string)$row['usename'],
                'duration_seconds' => $durationSeconds,
                'query' => (string)$row['query'],
                'error' => $error
            ));
        }
    }

    echo '<tr>';
    echo '<td>' . $pid . '</td>';
    echo '<td>' . htmlspecialchars((string)$row['usename']) . '</td>';
    echo '<td>' . htmlspecialchars((string)$row['state']) . '</td>';
    echo '<td>' . htmlspecialchars($waitEvent ?: '-') . '</td>';
    echo '<td>' . htmlspecialchars((string)$row['duration']) . ' ('
        . number_format($durationSeconds, 3, '.', '') . 's)</td>';
    echo '<td style="max-width:800px; white-space:pre-wrap; word-break:break-word;">'
        . htmlspecialchars((string)$row['query']) . '</td>';
    echo '<td>' . htmlspecialchars($action) . '</td>';
    echo '</tr>';
}

echo '</table>';
echo '<p>Connections used: ' . $connectionsUsed . ' / ' . $maxConnections
    . ' (' . round($usagePercent, 2) . '%), Query timeout: ' . $queryTimeout . 's.</p>';

if ($jagaServer && !empty($notifications)) {
    $header = "AutoCancel DB {$serverLabel} - canceled: {$canceledCount}, failed: {$failedCount}";
    callApiTelegram($header . "\n" . implode("\n", $notifications));
}

pg_close($conn);
?>
