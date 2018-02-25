<?php

// error_reporting(E_ALL);
// ini_set('display_errors', 1);
// ini_set('error_reporting', E_ALL); 
	
include('global_vars.php');
include('functions.php');

function killlock(){
    global $lockfile;
	exec("rm -rf $lockfile");
}

console_output("ZEUS Controller - v 1.2.1 alpha");

$task = $argv[1];

if($task == 'update_miner_stats')
{
	$lockfile = dirname(__FILE__) . "/console.update_miner_stats.loc";
	if(file_exists($lockfile)){
		console_output("update_miner_stats is already running. exiting");
		die();
	}else{
		exec("touch $lockfile");
	}
	
	console_output("Getting existing site miners");

	$miners_raw = file_get_contents("http://zeus.deltacolo.com/api/?key=".$config['api_key']."&c=site_miners");
	$miners = json_decode($miners_raw, true);

	console_output("Processing miners for '".$miners['site']['name']."'");

	if(is_array($miners))
	{
		foreach($miners['miners'] as $miner)
		{
			console_output("Checking " . $miner['id'] . " / " . $miner['name'] . " / " . $miner['hardware'] . " / " . $miner['ip_address']);

			if(ping($miner['ip_address']) == 'alive'){
				if($miner['hardware'] == 'ebite9' || $miner['hardware'] == 'ebite9plus' || $miner['hardware'] == 'ebite10')
				{
					$username 	= 'admin';
					$password 	= 'admin';
					$loginUrl 	= 'http://'.$miner['ip_address'].'/user/login/';

					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, $loginUrl);
					curl_setopt($ch, CURLOPT_POST, 1);
					curl_setopt($ch, CURLOPT_POSTFIELDS, 'username='.$username.'&word='.$password);
					curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					$store = curl_exec($ch);
					
					// get basic stats 
					curl_setopt($ch, CURLOPT_URL, 'http://'.$miner['ip_address'].'/alarm/GetAlarmLoop');
					$content = curl_exec($ch);
					$stats = json_decode($content, TRUE);

					if( $stats['feedback']['poolAlarm'] == 0 ) {
						$mining = 'mining';
					}else{
						$mining = 'offline';
					}

					$miner['update']['hardware']				= 'ebite9plus';
					$miner['update']['status']					= $mining;
					$miner['update']['frequency'] 				= $stats['feedback']['pllValue'];
					$miner['update']['hashrate'] 				= str_split($stats['feedback']['calValue']);
					$miner['update']['pcb_temp_1']				= $stats['feedback']['tmpValue'];
					$miner['update']['pcb_temp_2']				= $stats['feedback']['tmpValue'];
					$miner['update']['pcb_temp_3']				= $stats['feedback']['tmpValue'];
					
					// get cgminer stats
					curl_setopt($ch, CURLOPT_URL, 'http://'.$miner['ip_address'].'/Cgminer/CgminerStatus');
					$content = curl_exec($ch);
					$stats = json_decode($content, TRUE);
					
					$miner['update']['accepted'] 				= $stats['feedback']['accepted'];
					$miner['update']['rejected'] 				= $stats['feedback']['rejected'];
					
					// get cgminer pool details
					curl_setopt($ch, CURLOPT_URL, 'http://'.$miner['ip_address'].'/Cgminer/CgminerGetVal');
					$content = curl_exec($ch);
					$stats = json_decode($content, TRUE);
					
					$miner['update']['pools'][0]['url'] 		= $stats['feedback']['Mip1'];
					$miner['update']['pools'][0]['user'] 		= $stats['feedback']['Mwork1'];
					$miner['update']['pools'][1]['url'] 		= $stats['feedback']['Mip2'];
					$miner['update']['pools'][1]['user'] 		= $stats['feedback']['Mwork2'];
					$miner['update']['pools'][2]['url'] 		= $stats['feedback']['Mip3'];
					$miner['update']['pools'][2]['user'] 		= $stats['feedback']['Mwork3'];
					
					// get more stats
					curl_setopt($ch, CURLOPT_URL, 'http://'.$miner['ip_address'].'/Status/getsystemstatus');
					$content = curl_exec($ch);
					$stats = json_decode($content, TRUE);
					
					$miner['update']['software_version'] 		= $stats['feedback']['systemsoftwareversion'];
					
				}
				else
				{
					$miner_data 	= request($miner['ip_address'], 'summary+stats+pools+lcd');
					
					if(is_array($miner_data))
					{
						// $miner_stats 	= request($miner['ip_address'], 'stats');
						// $miner_pools 	= request($miner['ip_address'], 'pools');
						// $miner_lcd 		= request($miner['ip_address'], 'lcd');

						if($miner_data['STATUS1']['Msg'] == 'CGMiner stats')
						{
							$miner['update']['hardware']				= $miner_data['CGMiner']['Type'];
							if(isset($miner_data['STATS1'])){$miner['update']['hardware'] = 'spondoolies';}

							// $miner['update']['hashrate']				= $miner_data['SUMMARY']['GHS 5s'];
							$miner['update']['hardware_errors']			= $miner_data['SUMMARY']['Hardware Errors'];
							$miner['update']['discarded']				= $miner_data['SUMMARY']['Discarded'];
							$miner['update']['accepted']				= $miner_data['POOL0']['Accepted'];
							$miner['update']['rejected']				= $miner_data['SUMMARY']['Rejected'];

							$miner['update']['software_version']		= $miner_data['STATUS']['Description'];
							if(isset($miner_data['STATS0']['frequency']))
							{
								$miner['update']['frequency']			= $miner_data['STATS0']['frequency'];
							}elseif($miner_data['STATS0']['frequency1']){
								$miner['update']['frequency']			= $miner_data['STATS0']['frequency1'];
							}else{
								$miner['update']['frequency']			= '0';
							}
							
							$miner['update']['pcb_temp_1']				= $miner_data['STATS0']['temp1'];
							$miner['update']['pcb_temp_2']				= $miner_data['STATS0']['temp2'];
							$miner['update']['pcb_temp_3']				= $miner_data['STATS0']['temp3'];
							$miner['update']['pcb_temp_4']				= $miner_data['STATS0']['temp4'];

							$miner['update']['chip_temp_1']				= $miner_data['STATS0']['temp2_1'];
							$miner['update']['chip_temp_2']				= $miner_data['STATS0']['temp2_2'];
							$miner['update']['chip_temp_3']				= $miner_data['STATS0']['temp2_3'];
							$miner['update']['chip_temp_4']				= $miner_data['STATS0']['temp2_4'];

							$miner['update']['asics_1']					= $miner_data['STATS0']['chain_acn1'];
							$miner['update']['asics_2']					= $miner_data['STATS0']['chain_acn2'];
							$miner['update']['asics_3']					= $miner_data['STATS0']['chain_acn3'];
							$miner['update']['asics_4']					= $miner_data['STATS0']['chain_acn4'];

							$miner['update']['chain_asic_1']			= $miner_data['STATS0']['chain_acs1'];
							$miner['update']['chain_asic_2']			= $miner_data['STATS0']['chain_acs2'];
							$miner['update']['chain_asic_3']			= $miner_data['STATS0']['chain_acs3'];
							$miner['update']['chain_asic_4']			= $miner_data['STATS0']['chain_acs4'];

							$miner['update']['hashrate_1']				= $miner_data['STATS0']['chain_rate1'];
							$miner['update']['hashrate_2']				= $miner_data['STATS0']['chain_rate2'];
							$miner['update']['hashrate_3']				= $miner_data['STATS0']['chain_rate3'];
							$miner['update']['hashrate_4']				= $miner_data['STATS0']['chain_rate4'];
							if($miner['update']['hardware'] == 'spondoolies'){
								$miner['update']['hashrate_1']			= $miner_data['STATS0']['ASICs total rate'];
								$miner['update']['pcb_temp_1']			= $miner_data['STATS0']['Temperature front'];
								$miner['update']['pcb_temp_2']			= $miner_data['STATS0']['Temperature rear top'];
								$miner['update']['pcb_temp_3']			= $miner_data['STATS0']['Temperature rear bot'];
							}
							if($miner['update']['hardware'] == 'Antminer S4'){
								echo print_r($miner_lcd, true);
								$miner['update']['hashrate_1']			= $miner_data['LCD0']['GHS5s'];
								$miner['update']['pcb_temp_1']			= $miner_data['LCD0']['temp'];
								$miner['update']['pcb_temp_2']			= $miner_data['LCD0']['temp'];
								$miner['update']['pcb_temp_3']			= $miner_data['LCD0']['temp'];
							}

							$miner['update']['pools'][0]['user']		= $miner_data['POOL0']['User'];
							$miner['update']['pools'][0]['url']			= str_replace("stratum+tcp://", "", $miner_data['POOL0']['URL']);
						}
						elseif($miner_data['STATUS1']['Msg'] == 'BMMiner stats')
						{
							$miner['update']['hardware']				= $miner_data['BMMiner']['Type'];
							$miner['update']['software_version']		= 'BMMiner' . $miner_data['BMMiner']['BMMiner'];				

							$miner['update']['hardware_errors']			= $miner_data['SUMMARY']['Hardware Errors'];
							$miner['update']['discarded']				= $miner_data['SUMMARY']['Discarded'];
							$miner['update']['accepted']				= $miner_data['SUMMARY']['Accepted'];
							$miner['update']['rejected']				= $miner_data['SUMMARY']['Rejected'];

							$miner['update']['frequency']				= $miner_data['STATS0']['frequency'];
							$miner['update']['pcb_temp_1']				= $miner_data['STATS0']['temp6'];
							$miner['update']['pcb_temp_2']				= $miner_data['STATS0']['temp7'];
							$miner['update']['pcb_temp_3']				= $miner_data['STATS0']['temp8'];
							$miner['update']['pcb_temp_4']				= '0';

							$miner['update']['chip_temp_1']				= $miner_data['STATS0']['temp2_6'];
							$miner['update']['chip_temp_2']				= $miner_data['STATS0']['temp2_7'];
							$miner['update']['chip_temp_3']				= $miner_data['STATS0']['temp2_8'];
							$miner['update']['chip_temp_4']				= '0';

							$miner['update']['asics_1']					= $miner_data['STATS0']['chain_acn6'];
							$miner['update']['asics_2']					= $miner_data['STATS0']['chain_acn7'];
							$miner['update']['asics_3']					= $miner_data['STATS0']['chain_acn8'];
							$miner['update']['asics_4']					= '0';

							$miner['update']['chain_asic_1']			= $miner_data['STATS0']['chain_acs6'];
							$miner['update']['chain_asic_2']			= $miner_data['STATS0']['chain_acs7'];
							$miner['update']['chain_asic_3']			= $miner_data['STATS0']['chain_acs8'];
							$miner['update']['chain_asic_4']			= '';

							$miner['update']['hashrate_1']				= $miner_data['STATS0']['chain_rate6'];
							$miner['update']['hashrate_2']				= $miner_data['STATS0']['chain_rate7'];
							$miner['update']['hashrate_3']				= $miner_data['STATS0']['chain_rate8'];
							$miner['update']['hashrate_4']				= $miner_data['STATS0']['chain_rate4'];


							$miner['update']['pools'][0]['user']		= $miner_data['POOL0']['User'];
							$miner['update']['pools'][0]['url']			= str_replace("stratum+tcp://", "", $miner_data['POOL0']['URL']);
						}
						$miner['update']['status']				=	"mining";

					}else{
						$miner['update']['status']				=	"offline";
					}
				}
			}else{
				$miner['update']['status']				=	"disconnected";
			}

			// get the MAC address
			// $miner['mac_address'] = exec("nmap -sP ".$miner['ip_address']." | grep MAC");

			echo print_r($miner['update']);
			
			$data_string = json_encode($miner);

			$ch = curl_init("http://zeus.deltacolo.com/api/?key=".$config['api_key']."&c=miner_update");                                                                      
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                                                  
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
				'Content-Type: application/json',                                                                                
				'Content-Length: ' . strlen($data_string))                                                                       
			);                                                                                                                   

			$result = curl_exec($ch);

			// $result = json_decode($result, true);

			// console_output("API Status: " . $result['status']);
			// console_output("API Message: " . $result['message']);

			console_output("");
			console_output("==================================================");
			console_output("");
		}
	}else{
		console_output("No miners for this site");
	}
	
	// kill lock file
	killlock();
}

if($task == "network_scan")
{	
	$lockfile = dirname(__FILE__) . "/console.network_scan.loc";
	if(file_exists($lockfile)){
		console_output("network_scan is already running. exiting");
		die();
	}else{
		exec("touch $lockfile");
	}
	
	console_output("Getting site IP ranges");

	$ip_ranges_raw = file_get_contents("http://zeus.deltacolo.com/api/?key=".$config['api_key']."&c=site_ip_ranges");
	$ip_ranges = json_decode($ip_ranges_raw, true);

	foreach($ip_ranges['ip_ranges'] as $ip_range){
		$subnets[] = $ip_range['ip_range'];
	}

	ini_set('max_execution_time', 500);
	$ports = array(4028);

	function check_sub($sub, $ports, $site_id)
	{
		global $config;

		$rigs = array();
		$count = 0;
		foreach($sub as $ips) {
			foreach(range(1,254) as $ip_oct_4){
				// build full ip address
				$ip = $ips . $ip_oct_4;

				$rigs[$count]['ip_address'] = $ip;

				// clean the buffer
				// flush(); ob_flush();

				// check the port number is open / online
				foreach($ports as $port){ 
					if(@fsockopen($ip,$port,$errno,$errstr,1)){
						$cgminer = "ONLINE";

						$miner['site_id']		= $site_id;
						$miner['ip_address'] 	= $ip;

						$data_string = json_encode($miner);

						$ch = curl_init("http://zeus.deltacolo.com/api/?key=".$config['api_key']."&c=miner_add");                                                                      
						curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
						curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                                                  
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
						curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
							'Content-Type: application/json',                                                                                
							'Content-Length: ' . strlen($data_string))                                                                       
						);                                                                                                                   

						$result = curl_exec($ch);
					} else {
						$cgminer = "OFFLINE !!!";
					}
					console_output($ip . " > " . $cgminer);

					$rigs[$count]['cgminer_status']		= $cgminer;
				}
			}
			$count++;
		} 

		// clean the buffer
		flush();

		return $rigs;
	}

	$rigs = check_sub($subnets, $ports, $ip_ranges['site']['id']);

	// killlock
	killlock();
}

if($task == "site_jobs")
{	
	$lockfile = dirname(__FILE__) . "/console.site_jobs.loc";
	if(file_exists($lockfile)){
		console_output("site_jobs is already running. exiting");
		die();
	}else{
		exec("touch $lockfile");
	}
	
	console_output("Getting site jobs");

	$site_jobs_raw = file_get_contents("http://zeus.deltacolo.com/api/?key=".$config['api_key']."&c=site_jobs");
	$site_jobs = json_decode($site_jobs_raw, true);

	// echo print_r($site_jobs, true);

	if(isset($site_jobs['jobs']))
	{
		foreach($site_jobs['jobs'] as $site_job){
			
			// echo print_r($site_job);
			
			if($site_job['job'] == 'reboot_miner')
			{
				if($site_job['miner']['hardware'] == 'ebite9plus')
				{
					console_output("Rebooting EBit E9 Plus");
					
					$loginUrl 	= 'http://'.$site_job['miner']['ip_address'].'/user/login/';
					
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, $loginUrl);
					curl_setopt($ch, CURLOPT_POST, 1);
					curl_setopt($ch, CURLOPT_POSTFIELDS, 'username='.$site_job['miner']['username'].'&word='.$site_job['miner']['password']);
					curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					$store = curl_exec($ch);

					// get basic stats 
					curl_setopt($ch, CURLOPT_URL, 'http://'.$site_job['miner']['ip_address'].'/update/resetcgminer');
					$content = curl_exec($ch);
					$stats = json_decode($content, TRUE);
				}
				else
				{
					$cmd = "sshpass -p".$site_job['miner']['password']." ssh -o StrictHostKeyChecking=no ".$site_job['miner']['username']."@".$site_job['miner']['ip_address']." '/sbin/reboot'";
					// console_output($cmd);
					console_output("Rebooting " . $site_job['miner']['ip_address']);
					exec($cmd);
				}
				
				$site_job['status'] = 'complete';
			}

			if($site_job['job'] == 'restart_cgminer')
			{
				$cmd = "sshpass -p".$site_job['miner']['password']." ssh -o StrictHostKeyChecking=no ".$site_job['miner']['username']."@".$site_job['miner']['ip_address']." '/etc/init.d/cgminer.sh stop'";
				console_output("Restarting CGMiner on " . $site_job['miner']['ip_address']);
				exec($cmd);
				
				$site_job['status'] = 'complete';
			}
			
			if($site_job['job'] == 'network_scan')
			{
				$ip_ranges_raw = file_get_contents("http://zeus.deltacolo.com/api/?key=".$config['api_key']."&c=site_ip_ranges");
				$ip_ranges = json_decode($ip_ranges_raw, true);

				foreach($ip_ranges['ip_ranges'] as $ip_range){
					$subnets[] = $ip_range['ip_range'];
				}

				ini_set('max_execution_time', 500);
				$ports = array(4028);

				function check_sub($sub, $ports, $site_id)
				{
					global $config;

					$rigs = array();
					$count = 0;
					foreach($sub as $ips) {
						foreach(range(1,254) as $ip_oct_4){
							// build full ip address
							$ip = $ips . $ip_oct_4;

							$rigs[$count]['ip_address'] = $ip;

							echo("touch ".$ip);
							
							// clean the buffer
							// flush(); ob_flush();

							// check the port number is open / online
							foreach($ports as $port){ 
								if(@fsockopen($ip,$port,$errno,$errstr,1)){
									$cgminer = "ONLINE";

									$miner['site_id']		= $site_id;
									$miner['ip_address'] 	= $ip;

									$data_string = json_encode($miner);

									$ch = curl_init("http://zeus.deltacolo.com/api/?key=".$config['api_key']."&c=miner_add");                                                                      
									curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
									curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                                                  
									curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
									curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
										'Content-Type: application/json',                                                                                
										'Content-Length: ' . strlen($data_string))                                                                       
									);                                                                                                                   

									$result = curl_exec($ch);
								} else {
									$cgminer = "OFFLINE !!!";
								}
								console_output($ip . " > " . $cgminer);

								$rigs[$count]['cgminer_status']		= $cgminer;
							}
						}
						$count++;
					} 

					// clean the buffer
					flush();

					return $rigs;
				}

				$rigs = check_sub($subnets, $ports, $ip_ranges['site']['id']);
				
				$site_job['status'] = 'complete';
			}

			$job['id']		= $site_job['id'];
			
			if($site_job['status'] == 'complete')
			{
				$data_string = json_encode($job);

				$ch = curl_init("http://zeus.deltacolo.com/api/?key=".$config['api_key']."&c=site_job_complete");                                                                      
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
				curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                                                  
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
					'Content-Type: application/json',                                                                                
					'Content-Length: ' . strlen($data_string))                                                                       
				);                                                                                                                   

				$result = curl_exec($ch);
			}

		}
	}else{
		console_output("No jobs.");
	}
	
	// killlock
	killlock();
}