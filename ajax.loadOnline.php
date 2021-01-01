<?php

    require_once(__DIR__.'/config.php');

    if(isset($_POST['key'])) {
        // connect to db
        $mysqli = new mysqli($_CONFIG['database']['host'], $_CONFIG['database']['username'], $_CONFIG['database']['password'], $_CONFIG['database']['name'], $_CONFIG['database']['port']);
        if (!$mysqli->connect_errno) {
            // Escape special characters, if any
            $key = substr($mysqli->real_escape_string($_POST['key']), 0, 255);
            $sql='SELECT `data` FROM `storage` WHERE `key` = "'.$key.'" ORDER BY `updated` DESC LIMIT 1;';
            if ($result = $mysqli->query($sql)) {
                while ($row = $result->fetch_assoc()) {
                    echo json_encode($row['data']);
                    mysqli_free_result($result);
                    $mysqli -> close();
                    die();
                }
            }
        }
    }
    echo 'false';