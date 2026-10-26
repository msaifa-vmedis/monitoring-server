<?php

require __DIR__ . '/settings-lib.php';
require __DIR__ . '/telegram.php';
require __DIR__ . '/report-lib.php';

$settings = loadSettings();
$jagaServer = isOnDutySettings($settings);

$config = require __DIR__ . '/config.php';
$serverKey = isset($_GET['server']) ? $_GET['server'] : '';

if (!isset($config['mysql'][$serverKey])) {
    die('Konfigurasi server MySQL tidak ditemukan.');
}

if (!isDatabaseActivityEnabled($settings, 'mysql', $serverKey)) {
    die('Aktivitas koneksi database ini dinonaktifkan.');
}

$dbConfig = $config['mysql'][$serverKey];

$conn = mysqli_connect(
    $dbConfig['host'],
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['database'],
    $dbConfig['port']
);

if (!$conn) {
    die('Koneksi gagal: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, $dbConfig['charset']);

$serverLabel = $dbConfig['label'];
$queryTimeout = 60;
$sleepTimeoutNormal = 300;
$sleepTimeoutPressure = 60;
$connectionUsageThreshold = 80.0;
$sleepCountThreshold = 50;

$maxConnections = 0;
$threadsConnected = 0;
$usagePercent = 0.0;
$sleepCount = 0;

$maxConnResult = mysqli_query($conn, "SHOW VARIABLES LIKE 'max_connections'");
if ($maxConnResult && ($maxConnRow = mysqli_fetch_assoc($maxConnResult))) {
    $maxConnections = (int)$maxConnRow['Value'];
}

$threadsResult = mysqli_query($conn, "SHOW STATUS LIKE 'Threads_connected'");
if ($threadsResult && ($threadsRow = mysqli_fetch_assoc($threadsResult))) {
    $threadsConnected = (int)$threadsRow['Value'];
}

if ($maxConnections > 0) {
    $usagePercent = ($threadsConnected / $maxConnections) * 100;
}

$sleepCountResult = mysqli_query($conn, "
    SELECT COUNT(*) AS total_sleep
    FROM INFORMATION_SCHEMA.PROCESSLIST
    WHERE USER NOT IN ('rdsadmin', 'event_scheduler', 'system user')
    AND COMMAND = 'Sleep'
    AND ID <> CONNECTION_ID()
");
if ($sleepCountResult && ($sleepCountRow = mysqli_fetch_assoc($sleepCountResult))) {
    $sleepCount = (int)$sleepCountRow['total_sleep'];
}

$activeSleepTimeout = ($usagePercent >= $connectionUsageThreshold || $sleepCount >= $sleepCountThreshold)
    ? $sleepTimeoutPressure
    : $sleepTimeoutNormal;

$query = "
    SELECT ID, USER, DB, COMMAND, TIME, STATE, INFO
    FROM INFORMATION_SCHEMA.PROCESSLIST
    WHERE USER NOT IN ('rdsadmin', 'event_scheduler', 'system user')
    AND ID <> CONNECTION_ID()
    AND (
        (COMMAND = 'Sleep' AND TIME > {$activeSleepTimeout})
        OR
        (COMMAND IN ('Query', 'Execute') AND INFO IS NOT NULL)
    )
    ORDER BY TIME DESC
";
$result = mysqli_query($conn, $query);

if (!$result) {
    die('Query error: ' . mysqli_error($conn));
}

echo '<table border="1" cellpadding="10">';
echo '<tr><th>ID</th><th>USER</th><th>STATE</th><th>DURATION</th><th>QUERY</th><th>ACTION</th></tr>';

$notifications = array();
$sleepActions = array();
$killedSleepCount = 0;
$failedSleepCount = 0;
$killedQueryCount = 0;
$failedQueryCount = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $isSleep = $row['COMMAND'] === 'Sleep';
    $durationSeconds = (int)$row['TIME'];
    $shouldKillQuery = !$isSleep && $durationSeconds > $queryTimeout;
    $action = $isSleep ? 'SLEEP TIMEOUT' : 'MONITORING';

    if ($jagaServer && ($isSleep || $shouldKillQuery)) {
        $kill_id = (int)$row['ID'];
        $killProcedure = $isSleep ? 'mysql.rds_kill' : 'mysql.rds_kill_query';
        $kill_query = "CALL {$killProcedure}($kill_id);";

        if (mysqli_query($conn, $kill_query)) {
            $action = 'KILLED';
            if ($isSleep) {
                $killedSleepCount++;
                $sleepActions[] = "Koneksi sleep ID {$kill_id} ({$durationSeconds}s) berhasil di-kill.";
            } else {
                $killedQueryCount++;
                $fullQuery = (string)$row['INFO'];
                $infoText = substr($fullQuery, 0, 160);
                $notifications[] = "KILL LONG QUERY DB {$serverLabel} (ID: {$kill_id}, Time: {$durationSeconds}s): {$infoText}";
                saveReport(array(
                    'type' => 'mysql_query',
                    'action' => 'KILL QUERY',
                    'status' => 'success',
                    'server' => $serverLabel,
                    'process_id' => $kill_id,
                    'user' => (string)$row['USER'],
                    'duration_seconds' => $durationSeconds,
                    'query' => $fullQuery
                ));
            }
        } else {
            $action = 'FAILED';
            if ($isSleep) {
                $failedSleepCount++;
                $sleepActions[] = "Koneksi sleep ID {$kill_id} ({$durationSeconds}s) gagal di-kill: " . mysqli_error($conn);
            } else {
                $failedQueryCount++;
                $killError = mysqli_error($conn);
                $notifications[] = "Gagal kill query DB {$serverLabel} ID {$kill_id}: " . $killError;
                saveReport(array(
                    'type' => 'mysql_query',
                    'action' => 'KILL QUERY',
                    'status' => 'failed',
                    'server' => $serverLabel,
                    'process_id' => $kill_id,
                    'user' => (string)$row['USER'],
                    'duration_seconds' => $durationSeconds,
                    'query' => (string)$row['INFO'],
                    'error' => $killError
                ));
            }
        }
    }

    // Koneksi sleep diproses untuk auto-kill, tetapi tidak ditampilkan pada tabel query aktif.
    if (!$isSleep) {
        echo '<tr>';
        echo '<td>' . (int)$row['ID'] . '</td>';
        echo '<td>' . htmlspecialchars((string)$row['USER']) . '</td>';
        echo '<td>' . htmlspecialchars((string)$row['STATE']) . '</td>';
        echo '<td>' . $durationSeconds . 's</td>';
        echo '<td style="max-width:800px; white-space:pre-wrap; word-break:break-word;">'
            . htmlspecialchars(substr((string)$row['INFO'], 0, 300)) . '</td>';
        echo '<td>' . htmlspecialchars($action) . '</td>';
        echo '</tr>';
    }
}

echo '</table>';
echo '<p>Threads connected: ' . (int)$threadsConnected . ' / ' . (int)$maxConnections . ' (' . round($usagePercent, 2) . '%), Sleep count: ' . (int)$sleepCount . ', Sleep timeout aktif: ' . (int)$activeSleepTimeout . 's, Query timeout: ' . (int)$queryTimeout . 's.</p>';

if (!empty($sleepActions)) {
    echo '<div style="margin-top:10px; padding:10px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc;">';
    echo '<strong>Auto-kill koneksi sleep — berhasil: ' . $killedSleepCount . ', gagal: ' . $failedSleepCount . '</strong>';
    echo '<ul style="margin:8px 0 0; padding-left:20px;">';
    foreach ($sleepActions as $sleepAction) {
        echo '<li>' . htmlspecialchars($sleepAction) . '</li>';
    }
    echo '</ul></div>';
}

if ($jagaServer && !empty($notifications)) {
    $header = "AutoKill Query DB {$serverLabel} - killed: {$killedQueryCount}, failed: {$failedQueryCount}";
    callApiTelegram($header . "\n" . implode("\n", $notifications));
}

mysqli_close($conn);
?>