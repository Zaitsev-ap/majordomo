<?php
chdir(dirname(__FILE__) . '/../');

include_once("./config.php");
include_once("./lib/loader.php");
include_once("./lib/threads.php");

set_time_limit(0);

include_once("./load_settings.php");

if (defined('DISABLE_WEBSOCKETS') && DISABLE_WEBSOCKETS == 1) {
    echo "Web-sockets disabled\n";
    exit;
}

SQLTruncateTable('cached_ws');
echo date("H:i:s") . " running " . basename(__FILE__) . PHP_EOL;

$checked_time = 0;
$latest_sent = time();
<<<<<<< HEAD
//$cycleVarName = 'ThisComputer.' . str_replace('.php', '', basename(__FILE__)) . 'Run';
$cycleVarNameRUN=str_replace('.php', '', basename(__FILE__)) . "Run";
setGlobal($cycleVarNameRUN, $latest_sent, 1);

=======
setGlobal((str_replace('.php', '', basename(__FILE__))) . 'Run', time(), 1);
$cycleVarName = 'ThisComputer.' . str_replace('.php', '', basename(__FILE__)) . 'Run';
if (defined('SETTINGS_SYSTEM_WEBSOCKETS_RESTART_TIMEOUT') && (int)SETTINGS_SYSTEM_WEBSOCKETS_RESTART_TIMEOUT >= 0) {
    $websocket_restart_timeout = (int)SETTINGS_SYSTEM_WEBSOCKETS_RESTART_TIMEOUT;
} else {
    $websocket_restart_timeout = 0;
}

if (defined('WEBSOCKETS_QUEUE_LIMIT') && (int)WEBSOCKETS_QUEUE_LIMIT > 0) {
    $websocket_queue_limit = (int)WEBSOCKETS_QUEUE_LIMIT;
} else {
    $websocket_queue_limit = 500;
}
>>>>>>> pr-2

clearTimeout('restartWebSocket');

while (1) {
<<<<<<< HEAD
    $time = time();
    if ($checked_time != $time) {
        $checked_time = $time;
        $queue = SQLSelect("SELECT * FROM cached_ws");
        if (isset($queue[0]['PROPERTY'])) {
            SQLTruncateTable('cached_ws');
            $total = count($queue);
            $sent_ok = 1;
            $properties = array();
            $values = array();
            for ($i = 0; $i < $total; $i++) {
                //$queue[$i]['PROPERTY']=mb_strtolower($queue[$i]['PROPERTY'],'UTF-8');
                if ($queue[$i]['POST_ACTION'] == 'PostProperty') {
                    $properties[] = $queue[$i]['PROPERTY'];
                    $values[] = $queue[$i]['DATAVALUE'];
                } else {
                    $dataValue = $queue[$i]['DATAVALUE'];
                    if (is_array(json_decode($dataValue, true))) {
                        $dataValue = json_decode($dataValue, true);
=======
    if ($checked_time != time()) {
        $checked_time = time();
        try {
            $queue = SQLSelect("SELECT * FROM cached_ws ORDER BY ADDED LIMIT " . $websocket_queue_limit);
            if (is_array($queue) && !empty($queue)) {
                $total = count($queue);
                $sent_ok = 1;
                $properties = array();
                $values = array();
                $post_property_keys = array();

                for ($i = 0; $i < $total; $i++) {
                    $row = $queue[$i];
                    $property = $row['PROPERTY'] ?? '';
                    $postAction = $row['POST_ACTION'] ?? 'PostProperty';
                    $dataValue = $row['DATAVALUE'] ?? '';

                    if ($property === '') {
                        continue;
>>>>>>> pr-2
                    }

                    if ($postAction == 'PostProperty') {
                        $decoded = json_decode($dataValue, true);
                        if (is_array($decoded)) {
                            $dataValue = $decoded;
                        }
                        $properties[] = $property;
                        $values[] = $dataValue;
                        $post_property_keys[] = $property;
                        continue;
                    }

                    $decoded = json_decode($dataValue, true);
                    if (is_array($decoded)) {
                        $dataValue = $decoded;
                    }

                    $sent = postToWebSocket($property, $dataValue, $postAction);
                    if ($sent) {
                        SQLExec("DELETE FROM cached_ws WHERE PROPERTY='" . DBSafe($property) . "'");
                    } else {
                        $sent_ok = 0;
                    }
                }

                if (count($properties) > 0) {
                    $sent = postToWebSocket($properties, $values, 'PostProperty');
                    if ($sent) {
                        foreach ($post_property_keys as $property) {
                            SQLExec("DELETE FROM cached_ws WHERE PROPERTY='" . DBSafe($property) . "'");
                        }
                    } else {
                        $sent_ok = 0;
                    }
                }

                if ($sent_ok) {
                    $latest_sent = time();
                    // saveToCache("MJD:$cycleVarName", $latest_sent);
                    setGlobal((str_replace('.php', '', basename(__FILE__))) . 'Run', $latest_sent, 1);
                    if ($websocket_restart_timeout > 0) {
                        setTimeout('restartWebSocket', 'sg("cycle_websocketsRun","");sg("cycle_websocketsControl","restart");', $websocket_restart_timeout);
                    } else {
                        clearTimeout('restartWebSocket');
                    }
                } else {
                    echo date("H:i:s") . ' Error while posting to websocket.' . "\n";
                }
            }
<<<<<<< HEAD

            if ($sent_ok) {
                $latest_sent = $time;
                // saveToCache("MJD:$cycleVarName", $latest_sent);
                setGlobal($cycleVarNameRUN, $latest_sent, 1);
                //setTimeout('restartWebSocket', 'sg("cycle_websocketsRun","");sg("cycle_websocketsControl","restart");', 5 * 60);
            } else {
                echo date("H:i:s") . ' Error while posting to websocket.' . "\n";
            }
=======
            unset($queue, $properties, $values, $post_property_keys);
        } catch (Throwable $e) {
            DebMes('cycle_wscache error: ' . $e->getMessage(), 'websockets');
            echo date("H:i:s") . ' cycle_wscache exception: ' . $e->getMessage() . "\n";
>>>>>>> pr-2
        }
    }
    if (isRebootRequired() || isset($_GET['onetime'])) {
        exit;
    }
    sleep(1);
}

DebMes("Unexpected close of cycle: " . "basename(__FILE__)");
