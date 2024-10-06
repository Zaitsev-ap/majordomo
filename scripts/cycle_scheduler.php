<?php

chdir(dirname(__FILE__) . '/../');

include_once("./config.php");
include_once("./lib/loader.php");
include_once("./lib/threads.php");

set_time_limit(0);

include_once("./load_settings.php");
include_once(DIR_MODULES . "control_modules/control_modules.class.php");

include_once(DIR_MODULES . 'scripts/scripts.class.php');

$ctl = new control_modules();
$sc = new scripts();
$checked_time = 0;

//$cycleVarName = 'ThisComputer.' . str_replace('.php', '', basename(__FILE__)) . 'Run';
$cycleVarNameRUN=str_replace('.php', '', basename(__FILE__)) . "Run";

setGlobal($cycleVarNameRUN, time(), 1);


echo date("H:i:s") . " running " . basename(__FILE__) . PHP_EOL;

while (1) {
    $time = time();
    if (($time - $checked_time) > 50) {
        $checked_time = $time;
        setGlobal($cycleVarNameRUN, $time, 1);
    }
    runScheduledJobs();
    $sc->checkScheduledScripts();
    if (isRebootRequired() || isset($_GET['onetime'])) {
        exit;
    }
    sleep(1);
}

DebMes("Unexpected close of cycle: " . basename(__FILE__));
