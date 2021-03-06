<?php

// version 1.3

$api_url = 'http://dashboard.miningcontrolpanel.com';

include('/mcp/global_vars.php');
include('/mcp/functions.php');

// console_output("Building deamon. May take up to 30 seconds.");

// sleep(30);

$runs                   = $argv[1];
$forced_lag             = $argv[2];
$forced_lag_counter     = 0;

$miners_raw 		= file_get_contents($api_url."/api/?key=".$config['api_key']."&c=site_miners");
$miners 			= json_decode($miners_raw, true);

if(isset($miners['miners']))
{
    foreach($miners['miners'] as $miner)
    {
    	$miner_ids[] = $miner['id'];
    }

    $count 				= count($miner_ids);

    console_output("Polling " . $count . " miners.");

    for ($i=0; $i<$runs; $i++) {
        console_output("Spawning children.");
        for ($j=0; $j<$count; $j++) {
        	// echo "Checking Miner: ".$miner_ids[$j]."\n";

            $pipe[$j] = popen("php -q /mcp/deamon_update_miner_stats.php -p='".$miner_ids[$j]."'", 'w');

            if(isset($argv[2]))
            {
                $forced_lag_counter = $forced_lag_counter + 1;
                // console_output($forced_lag_counter);
                if($forced_lag_counter == $forced_lag)
                {
                    // console_output("forced_lag_counter = " . $forced_lag_counter);
                    sleep(1);
                    // console_output("done sleeping");
                    $forced_lag_counter = 0;
                }
            }
        }

        // console_output("Killing children.");
        
        // wait for them to finish
        for ($j=0; $j<$count; ++$j) {
            pclose($pipe[$j]);
        }

        // console_output("Sleeping.");
        // sleep(1);
    }
}else{
    console_output("No ASIC miners.");
}

exit();

?>