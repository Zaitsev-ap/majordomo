<?php
/**
 * CONNECT MQTT CLIENT
 *
 * @package MajorDoMo
 * @author Serge Dzheigalo <sergejey@gmail.com> https://majordomohome.com/
 * @version 1.3
 */
chdir(dirname(__FILE__) . '/../');

include_once("./config.php");
include_once("./lib/loader.php");

include_once("./load_settings.php");

$started_time = time();
$max_run_time = 2 * 60 * 60; // do restart in 2 hours

set_time_limit(0);

include_once(DIR_MODULES . 'connect/connect.class.php');

$menu_sent_time = 0;
$devices_sent_time = 0;

include_once(ROOT . "3rdparty/phpmqtt/phpMQTT.php");

$saved_devices_data = array();

const CONNECT_HOST = 'connect.smartliving.ru';

$simple_devices_queue_checked = 0;

setGlobal((str_replace('.php', '', basename(__FILE__))) . 'Run', time(), 1);

while (1) {

    DebMes("STARTING CONNECT CYCLE", 'connect');
    $connect = new connect();
    $connect->getConfig();

    if (!$connect->config['CONNECT_SYNC']) {
        echo "Connect sync turned off.";
        exit;
    }

    $devices_data = array();
    $devices_data_queue = checkOperationsQueue('connect_device_data');

    $sync_required = checkOperationsQueue('connect_sync_devices');
    echo date('Y-m-d H:i:s') . " Sending all devices\n";
    DebMes("Sending all devices", 'connect');
    $devices_sent_time = time();
    $connect->sendAllDevices();
    DebMes("Devicese sent.", 'connect');
    $saved_devices_data = array(); // clear sent data cache


    $sqlQuery = "SELECT * FROM commands";
    $commands = SQLSelect($sqlQuery);
    $total = count($commands);

    for ($i = 0; $i < $total; $i++) {
        $cmd_values[$commands[$i]['ID']] = $commands[$i]['CUR_VALUE'];
        $cmd_titles[$commands[$i]['ID']] = $commands[$i]['RENDER_TITLE'];
        $cmd_data[$commands[$i]['ID']] = $commands[$i]['RENDER_DATA'];
    }

    $username = strtolower($connect->config['CONNECT_USERNAME']);
    $password = $connect->config['CONNECT_PASSWORD'];

    $host = CONNECT_HOST;
    if ($connect->config['CONNECT_INSECURE']) {
        $port = '1883';
        $ca_file = NULL;
    } else {
        $port = '8883';
        $ca_file = dirname(__FILE__) . '/../modules/connect/fullchain.pem';
    }

    $connect_topics = array(
        '/incoming_urls', '/menu_session', '/reverse_requests', '/forward/#', '/ping'
    );


    $topics = array();
    foreach ($connect_topics as $topic) {
        $topics[] = $username . $topic;
        if ($username != $connect->config['CONNECT_USERNAME']) {
            $topics[] = $connect->config['CONNECT_USERNAME'] . $topic;
        }
    }

    $query = implode(',', $topics);
    $ping_topic = $username . '/ping';
    $client_name = "MJD_" . $username . "_" . time();
    $mqtt_client = new Bluerhinos\phpMQTT($host, $port, $client_name, $ca_file);

    echo date('H:i:s') . " Connecting to $host:$port\n";
    DebMes("Connecting to $host:$port", 'connect');
    if ($mqtt_client->connect(true, NULL, $username, $password)) {
        echo date('H:i:s') . " CONNECTED\n";
        DebMes("CONNECTED.", 'connect');
        $query_list = explode(',', $query);
        $total = count($query_list);
        echo date('H:i:s') . " Topics to watch: $query (Total: $total)\n";
        setGlobal((str_replace('.php', '', basename(__FILE__))) . 'Run', time(), 1);
        $topics = array();
        for ($i = 0; $i < $total; $i++) {
            $path = trim($query_list[$i]);
            echo date('H:i:s') . " Path: $path\n";
            $topics[$path] = array("qos" => 0, "function" => "procmsg");
        }
        foreach ($topics as $k => $v) {
            echo date('H:i:s') . " Subscribing to: $k\n";
            DebMes("Subscribing to $k", 'connect');
        }
        $mqtt_client->subscribe($topics, 0);
        echo date('H:i:s') . " SUBSCRIBED\n";
        DebMes("SUBSCRIBED.", 'connect');
        $ping_timestamp = 0;
        while ($mqtt_client->proc()) {
            $currentMillis = round(microtime(true) * 10000);
            if ($currentMillis - $previousMillis > 10000) {
                $previousMillis = $currentMillis;
                $checked_time = time();
                setGlobal((str_replace('.php', '', basename(__FILE__))) . 'Run', time(), 1);
                if (isRebootRequired() || isset($_GET['onetime'])) {
                    exit;
                }
            }

            if (!defined('DISABLE_SIMPLE_DEVICES') && (time() - $simple_devices_queue_checked >= 5)) {
                $simple_devices_queue_checked = time();
                $devices_data_queue = checkOperationsQueue('connect_device_data');
                foreach ($devices_data_queue as $property_data) {
                    $devices_data[] = $property_data;
                }
                $total_updated_devices = count($devices_data);
                if (isset($devices_data[0])) {
                    $property_data = array_shift($devices_data);
                    DebMes("Devices data queue size: " . $total_updated_devices, 'connect');
                    //echo date('Y-m-d H:i:s') . " Sending updated devices data (" . count($devices_data) . ")\n";
                    //DebMes("Sending updated devices data (" . count($devices_data) . ")", 'connect');
                    //foreach ($devices_data as $property_data) {
                        if (!isset($saved_devices_data[$property_data['DATANAME']]) || $saved_devices_data[$property_data['DATANAME']] != $property_data['DATAVALUE']) {
                            $saved_devices_data[$property_data['DATANAME']] = $property_data['DATAVALUE'];
                            DebMes("Sending " . $property_data['DATANAME'] . ": " . $property_data['DATAVALUE'], 'connect');
                            $connect->sendDeviceProperty($property_data['DATANAME'], $property_data['DATAVALUE']);
                            DebMes("Sent " . $property_data['DATANAME'], 'connect');
                        }
                    //}
                }
                $sync_required = checkOperationsQueue('connect_sync_devices');
                if ((time() - $devices_sent_time > 60 * 60) || is_array($sync_required[0])) {
                    echo date('Y-m-d H:i:s') . " Sending all devices\n";
                    DebMes("Sending all devices", 'connect');
                    $devices_sent_time = time();
                    $devices_data = array();
                    $connect->sendAllDevices();
                    DebMes("Devices sent.", 'connect');
                    $saved_devices_data = array(); // clear sent data cache
                }

            }

            if ((time() - $started_time) > $max_run_time) {
                DebMes("Running too long, exit.", 'connect');
                echo "Exit cycle CONNECT... (reconnecting)";
                $mqtt_client->close();
                exit;
            }
            if ((time() - $ping_timestamp) > 60) {
                $ping_timestamp = time();
                set_time_limit(10);
                echo date('Y-m-d H:i:s') . " Pinging MQTT server ($ping_topic) with timestamp " . time() . "\n";
                DebMes("Pinging MQTT server ($ping_topic) with timestamp " . time(), 'connect');
                $bytes_published = $mqtt_client->publish($ping_topic, time());
                if ($bytes_published) {
                    echo date('Y-m-d H:i:s') . " PING OK (" . $bytes_published . ")\n";
                    DebMes("Pinging MQTT server OK", 'connect');
                    setGlobal((str_replace('.php', '', basename(__FILE__))) . 'Run', time(), 1);
                } else {
                    echo date('Y-m-d H:i:s') . " PING FAILED\n";
                    DebMes("Pinging MQTT server FAILED", 'connect');
                    break;
                }
                set_time_limit(0);
            }
        }
        DebMes("Closing MQTT connection", 'connect');
        $mqtt_client->close();

    } else {
        echo date('Y-m-d H:i:s') . " Failed to connect ...\n";
        //setGlobal((str_replace('.php', '', basename(__FILE__))) . 'Run', time(), 1);
        DebMes("Failed to connect to MQTT", 'connect');
        sleep(10);
        continue;
    }
}

function procmsg($topic, $msg)
{
    echo date("Y-m-d H:i:s") . " Topic:{$topic} $msg\n";
    DebMes("Processing incoming $topic: $msg",'connect');
    if (preg_match('/menu_session/is', $topic)) {
        echo date("Y-m-d H:i:s") . " Menu session\n";
        $parent_id = $msg;
        if ($parent_id == 0) {
            global $menu_sent_time;
            if ((time() - $menu_sent_time) > 30) {
                $menu_sent_time = time();
                //DebMes("Sending full menu.",'connect');
                send_all_menu();
                //DebMes("OK",'connect');
            }
        } else {
            echo date('Y-m-d H:i:s') . " Sending menu element $parent_id\n";
            //DebMes("Sending menu element: $parent_id",'connect');
            update_menu_data($parent_id);
            //DebMes("OK",'connect');
        }
    } elseif (preg_match('/incoming_urls/is', $topic)) {
        $url = BASE_URL . $msg;
        echo date("Y-m-d H:i:s") . " Incoming url: $url\n";
        //DebMes("Incoming URL: $url",'connect');
        getURLBackground($url, 0);
    } elseif (preg_match('/reverse_urls/is', $topic)) {
        //DebMes("Reverse URL: $msg",'connect');
        $url = BASE_URL . '/ajax/connect.html?no_session=1&op=reverse_request&msg=' . urlencode($msg);
        echo date("Y-m-d H:i:s") . " Incoming reverse url: $msg\n";
        getURLBackground($url, 0);
    } elseif (preg_match('/\/forward\/(.+)/is', $topic, $m)) {
        $forward_topic = $m[1];
        //DebMes("Forward $forward_topic: $msg",'connect');
        callAPI('/api/module/mqtt', 'GET', array('topic' => $forward_topic, 'msg' => $msg));
    } elseif (preg_match('/reverse_requests/is', $topic)) {
        //DebMes("Reverse request: $msg",'connect');
        $url = BASE_URL . '/ajax/connect.html?no_session=1&op=reverse_request_full&msg=' . urlencode($msg);
        echo date("Y-m-d H:i:s") . " Incoming reverse request: $msg\n";
        getURLBackground($url, 0);
        //echo "URL: ".$url."\n";
        //$result = getURL($url, 0);
        //echo "Result: ".$result."\n";
    }
    //DebMes("Processing complete.",'connect');

}

function send_all_menu()
{
    global $connect;
    echo date('Y-m-d H:i:s') . " Sending full menu\n";
    update_menu_data(0);
    $connect->sendMenu(1);
}

function update_menu_data($element_id = 0)
{
    global $connect;
    global $cmd_values;
    global $cmd_titles;
    global $cmd_data;

    $qry = 1;
    if ($element_id) {
        $qry .= " AND (ID=" . (int)$element_id . " OR PARENT_ID=" . (int)$element_id . ")";
    }
    $sqlQuery = "SELECT * FROM commands WHERE $qry";
    $commands = SQLSelect($sqlQuery);
    $total = count($commands);
    $changed_items = array();
    for ($i = 0; $i < $total; $i++) {
        $old_render_title = $commands[$i]['RENDER_TITLE'];
        $new_render_title = processTitle($commands[$i]['TITLE'], $connect);
        if ($old_render_title != $new_render_title) {
            $commands[$i]['RENDER_TITLE'] = $new_render_title;
        }
        $old_render_data = $commands[$i]['RENDER_DATA'];
        $new_render_data = '';
        if ($commands[$i]['DATA'] != '') {
            $new_render_data = processTitle($commands[$i]['DATA'], $connect);
            if (strlen($new_render_data) > 50 * 1024) {
                $new_render_data = substr($new_render_data, 0, 50 * 1024);
            }
            $commands[$i]['RENDER_DATA'] = $new_render_data;
        }
        if ($new_render_title != $old_render_title || $new_render_data != $old_render_data) {
            $commands[$i]['RENDER_UPDATED'] = date('Y-m-d H:i:s');
            SQLUpdate('commands', $commands[$i]);
        }
        $changed = 0;
        if ($cmd_values[$commands[$i]['ID']] != $commands[$i]['CUR_VALUE']) {
            $cmd_values[$commands[$i]['ID']] = $commands[$i]['CUR_VALUE'];
            $changed = 1;
        }
        if ($cmd_titles[$commands[$i]['ID']] != $commands[$i]['RENDER_TITLE']) {
            $cmd_titles[$commands[$i]['ID']] = $commands[$i]['RENDER_TITLE'];
            $changed = 1;
        }
        if ($cmd_data[$commands[$i]['ID']] != $commands[$i]['RENDER_DATA']) {
            $cmd_data[$commands[$i]['ID']] = $commands[$i]['RENDER_DATA'];
            $changed = 1;
        }
        if ($changed) {
            $changed_items[] = $commands[$i];
        }

    }
    $total = count($changed_items);
    if ($total > 0 && $element_id > 0) {
        //echo "Sending changed items: ".json_encode($changed_items)."\n";
        $connect->sendMenuItems($changed_items);
    } elseif ($element_id > 0) {
        //echo "Items not changed\n";
    }
}
