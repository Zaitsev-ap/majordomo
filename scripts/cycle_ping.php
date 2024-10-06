<?php

chdir(dirname(__FILE__) . '/../');

include_once './config.php';
include_once './lib/loader.php';
include_once './lib/threads.php';

set_time_limit(0);

include_once("./load_settings.php");
include_once(DIR_MODULES . "control_modules/control_modules.class.php");

$ctl = new control_modules();

include_once(DIR_MODULES . 'pinghosts/pinghosts.class.php');

$pinghosts = new pinghosts();

$checked_time = 0;
setGlobal((str_replace('.php', '', basename(__FILE__))).'Run', time(), 1);
$cycleVarName='ThisComputer.'.str_replace('.php', '', basename(__FILE__)).'Run';

//$cycleVarName='ThisComputer.'.str_replace('.php', '', basename(__FILE__)).'Run';
$cycleVarNameRUN=str_replace('.php', '', basename(__FILE__)) . "Run";

setGlobal($cycleVarName, time(), 1);
echo date("H:i:s") . " running " . basename(__FILE__) . PHP_EOL;

while (1)
{
   $time = time();
if ($time - $checked_time > 10)
   {
      $checked_time = time();
      if ($time - $cycle_checked_time > 50) {
            $cycle_checked_time = $time;
            setGlobal($cycleVarNameRUN, $time, 1);
        }
      
      
      // checking all hosts
      $pinghosts->checkAllHosts();
   }

   if (file_exists('./reboot') || IsSet($_GET['onetime']))
   {
      exit;
   }
   sleep(1);
}

DebMes("Unexpected close of cycle: " . basename(__FILE__));
