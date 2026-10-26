<?php

function callApiTelegram($message = "Server Up")
{
    require_once __DIR__ . '/settings-lib.php';
    $settings = loadSettings();
    if (empty($settings['telegram_enabled'])) {
        return false;
    }

    $config = require __DIR__ . '/config.php';

    if (!isset($config['telegram'])) {
        return false;
    }

    $telegramConfig = $config['telegram'];
    $botToken = $telegramConfig['bot_token'];
    $chatId = $telegramConfig['chat_id'];
    $timeout = isset($telegramConfig['timeout']) ? (int)$telegramConfig['timeout'] : 3;
    $connectTimeout = isset($telegramConfig['connect_timeout'])
        ? (int)$telegramConfig['connect_timeout']
        : 3;

    $telegramApiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $data = array(
        'chat_id' => $chatId,
        'text' => $message
    );

    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $telegramApiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);

        $response = curl_exec($ch);
        $hasError = curl_errno($ch) !== 0;
        $errorMessage = $hasError ? curl_error($ch) : '';
        curl_close($ch);

        if (isset($_GET['test'])) {
            if ($hasError) {
                echo 'Error: ' . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8');
            } else {
                echo 'Response dari Telegram: ' . htmlspecialchars((string)$response, ENT_QUOTES, 'UTF-8');
            }
        }

        return !$hasError;
    } catch (Exception $e) {
        return false;
    }
}
