<?php

function getReportDirectory()
{
    return __DIR__ . '/reports';
}

function cleanupOldReports()
{
    $directory = getReportDirectory();
    if (!is_dir($directory)) {
        return;
    }

    $minimumDate = date('Y-m-d', strtotime('-6 days'));
    foreach (glob($directory . '/report-*.json') as $file) {
        if (preg_match('/report-(\d{4}-\d{2}-\d{2})\.json$/', $file, $matches) && $matches[1] < $minimumDate) {
            @unlink($file);
        }
    }
}

function saveReport($event)
{
    $directory = getReportDirectory();
    if (!is_dir($directory) && !@mkdir($directory, 0770, true)) {
        return false;
    }

    cleanupOldReports();

    $event['timestamp'] = date('Y-m-d H:i:s');
    $event['date'] = date('Y-m-d');
    $file = $directory . '/report-' . $event['date'] . '.json';
    $handle = @fopen($file, 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) {
        if ($handle) {
            fclose($handle);
        }
        return false;
    }

    rewind($handle);
    $contents = stream_get_contents($handle);
    $reports = $contents !== '' ? json_decode($contents, true) : array();
    if (!is_array($reports)) {
        $reports = array();
    }
    $reports[] = $event;

    ftruncate($handle, 0);
    rewind($handle);
    $written = fwrite($handle, json_encode($reports, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return $written !== false;
}

function getAvailableReportDates()
{
    cleanupOldReports();
    $dates = array();
    foreach (glob(getReportDirectory() . '/report-*.json') as $file) {
        if (preg_match('/report-(\d{4}-\d{2}-\d{2})\.json$/', $file, $matches)) {
            $dates[] = $matches[1];
        }
    }
    rsort($dates);
    return $dates;
}

function loadReportsByDate($date)
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return array();
    }
    $file = getReportDirectory() . '/report-' . $date . '.json';
    if (!is_file($file)) {
        return array();
    }
    $reports = json_decode(file_get_contents($file), true);
    return is_array($reports) ? $reports : array();
}
