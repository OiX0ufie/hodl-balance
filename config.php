<?php

    $_CONFIG = [
        'onlineKeyLength' => 24, // minimum length of online key
        'homeUrl' => false, // optional: link to parent page; default: disabled
        'topCenterMarkup' => false,    // inject markup in the top center portion (e.g. additional link to external webpage)
        'balanceCommand' => 'lolcat -f | aha -n',    // customize lolcat/aha pipe. See balance.php "echo $balanceData | $CONFIG['balanceCommand']"
        'database' => [
            'name' => 'hodl-balance',
            'username' => 'hodl-balance',
            'password' => '',
            'host' => 'localhost',
            'port' => 3306,
        ],
    ];

    date_default_timezone_set('UTC');

    // load optional custom config file (to overwrite project default config)
    if(file_exists(__DIR__.'/config.local.php') && is_readable(__DIR__.'/config.local.php')) {
        require_once(__DIR__.'/config.local.php');
    }