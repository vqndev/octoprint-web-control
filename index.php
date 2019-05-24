<?php

require_once('./vendor/autoload.php');

// Gather the query variables
$apikey = $_GET['apikey'] ?? '';
$voicePrinter = $_GET['printer'] ?? '';
$cmd = $_GET['cmd'] ?? '';
$selectedPrinter = null;

// Ghetto API Authentication
if($apikey !== 'MySuperSecreeeeetAPIKEYY234'){
  die('not authorized');
}

// Google assistant populates the $voicePrinter variable, use regex to figure out the printer
$voicePrinter = str_replace(' ',  '', strtolower($voicePrinter)); // 'the A 10 m' turns to 'thea10m'
if(preg_match('/mercury|a10m/', $voicePrinter)){
  $selectedPrinter = 'a10m';
} elseif(preg_match('/venus|e180/', $voicePrinter)){
  $selectedPrinter = 'e180';
} elseif(preg_match('/earth|a2|anet/', $voicePrinter)){
  $selectedPrinter = 'a2';
} elseif(preg_match('/mars|tevo|little|monster/', $voicePrinter)){
  $selectedPrinter = 'tlm';
} 

// Don't do anything if we can't match a printer
if(!$selectedPrinter){
  die('no printer selected');
}

// Preheat command
if($cmd === 'preheat'){
  $status = octoprint_call($selectedPrinter, 'get /api/connection')->get('data.current.state');
  if( $status !== 'Operational' && $status !== 'Printing' ) {
    octoprint_call($selectedPrinter, 'command /api/connection connect');
  } else{
    echo 'already connected'; // Cool, we don't need to connect to it
  }
  
  if ($status !== 'Printing'){
    // A10M Needs to heat dual extruders, TODO: make this more elegant
    if($selectedPrinter === 'a10m'){
      octoprint_call($selectedPrinter, "post_json /api/printer/command '{ \"commands\": [\"M104 T0 S210\", \"M104 T1 S210\", \"M140 S60\"] }'");
    } else {
      octoprint_call($selectedPrinter, "post_json /api/printer/command '{ \"commands\": [\"M104 T0 S210\", \"M140 S60\"] }'");
    }
  }
  
  
}
/*
 * IMPORTANT TODO: SANITIZE the command line string 
 */
function octoprint_call($printer = '', $command = '') {
  $results = [];
  $printer_connection = get_printers()->get($printer);
  if($printer_connection === null){
    die("Printer $printer does not exist ");
  }
  exec("/home/pi/oprint/bin/octoprint client $printer_connection $command", $op);
  $json_string = '';
  $results['status_code'] = 0;
  foreach($op as $line_index => $line){
    if($line_index === 0){
       preg_match('/\d+/', $line, $statusMatches);
      if(!empty($statusMatches[0])){
        $results['status_code'] = $statusMatches[0];
      }
    }else{
      $json_string .= $line."\n";
    }
  }
  $results['data'] = json_decode(json_encode(json_decode($json_string)), true);

  // var_dump($results);
  return dot($results);
}

function get_printers(){
  // -a is the apikey
  // -h is the host
  return dot([
    "a2" => "", // The webserver is running on the raspberry pi of the A2, so if we don't add any connection parameters, it'll just defaul to this one
    "a10m" => "-a A193DC20962E424FA83009F45B049B25 -h 192.168.1.1 -p 80", // This info is not real
    "tlm" => "-a A193DC20962E424FA83009F45B049B25 -h 192.168.1.2 -p 80",
    "e180" => "-a A193DC20962E424FA83009F45B049B25 -h 192.168.1.3 -p 80",
  ]);
}