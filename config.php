<?php

    $_CONFIG = [
        'homeUrl' => false, // optional: link to parent page; default: disabled
        'balanceCommand' => 'lolcat -f | aha -n',    // customize lolcat/aha pipe. See balance.php "echo $balanceData | $CONFIG['balanceCommand']"
    ];

    // load optional custom config file (to overwrite project default config)
    if(file_exists(__DIR__.'/config.local.php') && is_readable(__DIR__.'/config.local.php')) {
        require_once(__DIR__.'/config.local.php');
    }