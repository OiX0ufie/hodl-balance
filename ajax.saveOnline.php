<?php

    require_once(__DIR__.'/config.php');

    if(isset($_POST['key']) && isset($_POST['data'])) {
        // connect to db
        $mysqli = new mysqli($_CONFIG['database']['host'], $_CONFIG['database']['username'], $_CONFIG['database']['password'], $_CONFIG['database']['name'], $_CONFIG['database']['port']);
        if (!$mysqli->connect_errno) {
            // Escape special characters, if any
            $key = substr($mysqli->real_escape_string($_POST['key']), 0, 255);
            $data = $mysqli->real_escape_string($_POST['data']);

            // check if data hast changed before saving
            $needsUpdate = true;
            $sql='SELECT `data` FROM `storage` WHERE `key` = "'.$key.'" ORDER BY `updated` DESC LIMIT 1;';
            if ($result = $mysqli->query($sql)) {
                while ($row = $result->fetch_assoc()) {
                    if($_POST['data'] == $row['data']) {
                        $needsUpdate = false;
                    }
                }
                mysqli_free_result($result);
            }
            if($needsUpdate) {
                $sql='INSERT INTO `storage` (`key`, `data`) VALUES ("'.$key.'", "'.$data.'");';
                if ($mysqli->query($sql)) {
                    $mysqli->close();
                    echo 'true';
                    die();
                }
            }
            else {
                $mysqli->close();
                echo 'true';
                die();
            }
        }
    }
    echo 'false';