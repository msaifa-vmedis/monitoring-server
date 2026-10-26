<?php

require __DIR__ . '/settings-lib.php';
require __DIR__ . '/telegram.php';

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
    SELECT ID, USER, COMMAND, TIME, INFO
    FROM INFORMATION_SCHEMA.PROCESSLIST
    WHERE USER NOT IN ('rdsadmin', 'event_scheduler', 'system user')
    AND ID <> CONNECTION_ID()
    AND (
        (COMMAND = 'Sleep' AND TIME > {$activeSleepTimeout})
        OR
        (COMMAND IN ('Query', 'Execute') AND TIME > {$queryTimeout})
    )
    ORDER BY TIME DESC
";
$result = mysqli_query($conn, $query);

if (!$result) {
    die('Query error: ' . mysqli_error($conn));
}

echo '<table border="1" cellpadding="10">';
echo '<tr><th>ID</th><th>USER</th><th>COMMAND</th><th>TIME</th><th>INFO</th><th>ACTION</th></tr>';

$notifications = [];
$killedCount = 0;
$failedCount = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $action = 'SKIPPED';
    echo '<tr>';
    echo '<td>' . htmlspecialchars($row['ID']) . '</td>';
    echo '<td>' . htmlspecialchars($row['USER']) . '</td>';
    echo '<td>' . htmlspecialchars($row['COMMAND']) . '</td>';
    echo '<td>' . htmlspecialchars($row['TIME']) . '</td>';
    // Gunakan (string) casting agar strpos tidak error jika INFO bernilai NULL
    echo '<td>' . htmlspecialchars(substr((string)$row['INFO'], 0, 200)) . '</td>';

    if ($jagaServer) {
        $kill_id = (int)$row['ID'];
        $isSleep = $row['COMMAND'] === 'Sleep';
        $killProcedure = $isSleep ? 'mysql.rds_kill' : 'mysql.rds_kill_query';
        $kill_query = "CALL {$killProcedure}($kill_id);";

        if (mysqli_query($conn, $kill_query)) {
            $killedCount++;
            $action = 'KILLED';
            $tipe = $isSleep ? 'KONEKSI SLEEP' : 'LONG QUERY';
            $infoText = $row['INFO'] ? substr((string)$row['INFO'], 0, 160) : 'NULL';
            $notifications[] = "KILL {$tipe} DB {$serverLabel} (ID: {$kill_id}, Time: {$row['TIME']}s): {$infoText}";
        } else {
            $failedCount++;
            $action = 'FAILED';
            $notifications[] = "Gagal kill proses DB {$serverLabel} ID {$kill_id}: " . mysqli_error($conn);
        }
    }

    echo '<td>' . htmlspecialchars($action) . '</td>';
    echo '</tr>';
}

echo '</table>';
echo '<p>Threads connected: ' . (int)$threadsConnected . ' / ' . (int)$maxConnections . ' (' . round($usagePercent, 2) . '%), Sleep count: ' . (int)$sleepCount . ', Sleep timeout aktif: ' . (int)$activeSleepTimeout . 's, Query timeout: ' . (int)$queryTimeout . 's.</p>';

if ($jagaServer && !empty($notifications)) {
    $header = "AutoKill DB {$serverLabel} - killed: {$killedCount}, failed: {$failedCount}";
    callApiTelegram($header . "\n" . implode("\n", $notifications));
}

mysqli_close($conn);
?>