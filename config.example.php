<?php

/*
 * Salin file ini menjadi config.php, lalu isi kredensial sebenarnya.
 * Tambahkan item baru untuk setiap server. Key item dipakai pada parameter ?server=.
 */
return array(
    'mysql' => array(
        'a' => array(
            'label' => 'A',
            'enabled' => true,
            'host' => 'MYSQL_HOST',
            'port' => 3306,
            'database' => 'MYSQL_DATABASE',
            'username' => 'MYSQL_USERNAME',
            'password' => 'MYSQL_PASSWORD',
            'charset' => 'latin1'
        )
    ),
    'telegram' => array(
        'bot_token' => 'TELEGRAM_BOT_TOKEN',
        'chat_id' => 'TELEGRAM_CHAT_ID',
        'timeout' => 3,
        'connect_timeout' => 3
    ),
    'app_monitor' => array(
        'restart_url' => 'RESTART_API_URL',
        'required_failures' => 3,
        'request_timeout' => 2,
        'apps' => array(
            'APP_UTAMA' => array(
                'label' => 'APP UTAMA',
                'status_url' => 'APP_STATUS_URL',
                'instance_id' => 'INSTANCE_ID',
                'threshold' => 1200,
                'enabled' => true
            )
        )
    ),
    'postgresql' => array(
        'vi' => array(
            'label' => 'VI',
            'enabled' => true,
            'host' => 'POSTGRESQL_HOST',
            'port' => 5432,
            'database' => 'POSTGRESQL_DATABASE',
            'username' => 'POSTGRESQL_USERNAME',
            'password' => 'POSTGRESQL_PASSWORD',
            'connect_timeout' => 10
        )
    )
);
