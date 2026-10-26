<?php

function getDefaultDateSchedule()
{
    $schedule = array();
    for ($date = 1; $date <= 31; $date++) {
        $schedule[(string)$date] = array('enabled' => true, 'start' => '00:00', 'end' => '23:59');
    }
    return $schedule;
}

function getDefaultSettings()
{
    return array(
        'timezone' => 'Asia/Jakarta',
        'schedule_enabled' => true,
        'schedule' => getDefaultDateSchedule(),
        'database_activity' => array(
            'mysql' => array('a' => true, 'b' => false, 'c' => true, 'd' => false, 'e' => true, 'f' => false),
            'postgresql' => array('vi' => true)
        ),
        'telegram_enabled' => true,
        'auto_restart_app' => true,
        'auto_refresh_page' => true,
        'auto_restart_minutes' => 10,
        'poll_interval_seconds' => 5
    );
}

function mergeSettings($defaults, $saved)
{
    foreach ($saved as $key => $value) {
        if (isset($defaults[$key]) && is_array($defaults[$key]) && is_array($value)) {
            $defaults[$key] = mergeSettings($defaults[$key], $value);
        } else {
            $defaults[$key] = $value;
        }
    }
    return $defaults;
}

function loadSettings()
{
    $defaults = getDefaultSettings();
    $file = __DIR__ . '/settings.json';
    if (!is_file($file)) {
        return $defaults;
    }

    $saved = json_decode(file_get_contents($file), true);
    if (!is_array($saved)) {
        return $defaults;
    }

    // Jadwal lama berbasis nama hari tidak digabungkan ke jadwal baru berbasis tanggal.
    if (isset($saved['schedule']) && isset($saved['schedule']['monday'])) {
        unset($saved['schedule']);
    }

    return mergeSettings($defaults, $saved);
}

function saveSettings($settings)
{
    return file_put_contents(
        __DIR__ . '/settings.json',
        json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    ) !== false;
}

function isOnDutySettings($settings)
{
    if (empty($settings['schedule_enabled'])) {
        return true;
    }

    $timezone = isset($settings['timezone']) ? $settings['timezone'] : 'Asia/Jakarta';
    try {
        $now = new DateTime('now', new DateTimeZone($timezone));
    } catch (Exception $e) {
        $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    }

    $date = (string)((int)$now->format('j'));
    if (!isset($settings['schedule'][$date]) || empty($settings['schedule'][$date]['enabled'])) {
        return false;
    }

    $today = $settings['schedule'][$date];
    $current = $now->format('H:i');
    $start = $today['start'];
    $end = $today['end'];

    if ($start <= $end) {
        return $current >= $start && $current <= $end;
    }

    return $current >= $start || $current <= $end;
}

function isDatabaseActivityEnabled($settings, $engine, $serverKey)
{
    return isset($settings['database_activity'][$engine][$serverKey])
        && $settings['database_activity'][$engine][$serverKey];
}
