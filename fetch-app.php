<?php

require __DIR__ . '/settings-lib.php';
require __DIR__ . '/telegram.php';
require __DIR__ . '/report-lib.php';

session_start();
$config = require __DIR__ . '/config.php';
$settings = loadSettings();
$jagaServer = isOnDutySettings($settings);
$appMonitor = $config['app_monitor'];

function fetchAppStatus($url, $timeout)
{
    $context = stream_context_create(array(
        'http' => array('timeout' => $timeout, 'ignore_errors' => true)
    ));
    return @file_get_contents($url, false, $context);
}

function restartAppInstance($url, $instanceId)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Accept: application/json'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $instanceId);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    $response = curl_exec($ch);
    $success = curl_errno($ch) === 0;
    curl_close($ch);
    return $success ? $response : false;
}

function extractCurrentRequests($html)
{
    if ($html && preg_match('/(\d+) requests currently being processed/', $html, $matches)) {
        return (int)$matches[1];
    }
    return null;
}

echo '<table><tr><th>APP</th><th>REQUEST AKTIF</th><th>THRESHOLD</th><th>PERCOBAAN</th><th>STATUS</th><th>ACTION</th></tr>';

foreach ($appMonitor['apps'] as $key => $app) {
    if (empty($app['enabled'])) {
        continue;
    }

    if (!isset($_SESSION['app_monitor'][$key])) {
        $_SESSION['app_monitor'][$key] = 0;
    }

    $html = fetchAppStatus($app['status_url'], (int)$appMonitor['request_timeout']);
    $currentRequests = extractCurrentRequests($html);
    $status = 'NORMAL';
    $action = '-';

    if ($currentRequests === null) {
        $_SESSION['app_monitor'][$key]++;
        $status = 'FETCH GAGAL';
    } elseif ($currentRequests > (int)$app['threshold']) {
        $_SESSION['app_monitor'][$key]++;
        $status = 'THRESHOLD';
    } else {
        $_SESSION['app_monitor'][$key] = 0;
    }

    if ($jagaServer && !empty($settings['auto_restart_app']) && $_SESSION['app_monitor'][$key] >= (int)$appMonitor['required_failures']) {
        $restartResult = restartAppInstance($appMonitor['restart_url'], $app['instance_id']);
        $action = $restartResult !== false ? 'RESTARTED' : 'RESTART FAILED';
        callApiTelegram('AUTO RESTART ' . $key . ' - request: ' . ($currentRequests === null ? 'UNKNOWN' : $currentRequests) . ' - ' . $action);
        saveReport(array(
            'type' => 'app_restart',
            'action' => 'RESTART APP',
            'status' => $restartResult !== false ? 'success' : 'failed',
            'server' => $key,
            'active_requests' => $currentRequests,
            'threshold' => (int)$app['threshold'],
            'instance_id' => $app['instance_id'],
            'details' => $action
        ));
        $_SESSION['app_monitor'][$key] = 0;
    } elseif (!$jagaServer) {
        $action = 'DI LUAR JADWAL';
    } elseif (empty($settings['auto_restart_app'])) {
        $action = 'AUTO RESTART OFF';
    }

    echo '<tr>';
    echo '<td><a target="_blank" href="' . htmlspecialchars($app['status_url']) . '">' . htmlspecialchars($app['label']) . '</a></td>';
    echo '<td class="active-request-value">' . ($currentRequests === null ? '-' : (int)$currentRequests) . '</td>';
    echo '<td>' . (int)$app['threshold'] . '</td>';
    echo '<td>' . (int)$_SESSION['app_monitor'][$key] . ' / ' . (int)$appMonitor['required_failures'] . '</td>';
    echo '<td>' . htmlspecialchars($status) . '</td>';
    echo '<td>' . htmlspecialchars($action) . '</td>';
    echo '</tr>';
}

echo '</table>';
