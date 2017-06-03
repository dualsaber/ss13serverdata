<?php
header('Access-Control-Allow-Origin: *');
// include("serverinfo.php");

if ($_GET['bagil'] == "1") {
	$servers[0] = Array();
	$servers[0]["address"] = "game.tgstation13.org";
	$servers[0]["port"] = 2337;
	$servers[0]["servername"] = "SS13: Server 1 (Badger)";
}
if ($_GET['sybil'] == "1") {
	$servers[1] = Array();
	$servers[1]["address"] = "game.tgstation13.org";
	$servers[1]["port"] = 1337;
	$servers[1]["servername"] = "SS13: Server 2 (Sybil)";
}
if ($_GET['cm'] == "1") {
	$servers[2] = Array();
	$servers[2]["address"] = "play.colonial-marines.com";
	$servers[2]["port"] = 1400;
	$servers[2]["servername"] = "CM";
}
if ($_GET['ftl'] == "1") {
	$servers[3] = Array();
	$servers[3]["address"] = "ftl13.com";
	$servers[3]["port"] = 7777;
	$servers[3]["servername"] = "FTL";
}
if ($_GET['goon1'] == "1") {
	$servers[3] = Array();
	$servers[3]["address"] = "ss13.lljk.net";
	$servers[3]["port"] = 26100;
	$servers[3]["servername"] = "FTL";
}
if ($_GET['goon2'] == "1") {
	$servers[3] = Array();
	$servers[3]["address"] = "lljk2.goonhub.com";
	$servers[3]["port"] = 26200;
	$servers[3]["servername"] = "FTL";
}


function export($addr, $port, $str) {
	global $error;
	// All queries must begin with a question mark (ie "?players")
	if($str{0} != '?') $str = ('?' . $str);

	/* --- Prepare a packet to send to the server (based on a reverse-engineered packet structure) --- */
	$query = "\x00\x83" . pack('n', strlen($str) + 6) . "\x00\x00\x00\x00\x00" . $str . "\x00";
	/* --- Create a socket and connect it to the server --- */
	$server = socket_create(AF_INET,SOCK_STREAM,SOL_TCP) or exit("ERROR");
	socket_set_option($server, SOL_SOCKET, SO_SNDTIMEO, array('sec' => 2, 'usec' => 0)); //sets connect and send timeout to 2 seconds
	if(!socket_connect($server,$addr,$port)) {
		$error = true;
		return "ERROR";
	}


	/* --- Send bytes to the server. Loop until all bytes have been sent --- */
	$bytestosend = strlen($query);
	$bytessent = 0;
	while ($bytessent < $bytestosend) {
		//echo $bytessent.'<br>';
		$result = socket_write($server,substr($query,$bytessent),$bytestosend-$bytessent);
		//echo 'Sent '.$result.' bytes<br>';
		if ($result===FALSE) die(socket_strerror(socket_last_error()));
		$bytessent += $result;
	}

	/* --- Idle for a while until recieved bytes from game server --- */
	$result = socket_read($server, 10000, PHP_BINARY_READ);
	socket_close($server); // we don't need this anymore

	if($result != "") {
		if($result{0} == "\x00" || $result{1} == "\x83") { // make sure it's the right packet format

			// Actually begin reading the output:
			$sizebytes = unpack('n', $result{2} . $result{3}); // array size of the type identifier and content
			$size = $sizebytes[1] - 1; // size of the string/floating-point (minus the size of the identifier byte)

			if($result{4} == "\x2a") { // 4-byte big-endian floating-point
				$unpackint = unpack('f', $result{5} . $result{6} . $result{7} . $result{8}); // 4 possible bytes: add them up together, unpack them as a floating-point
				return $unpackint[1];
			}
			else if($result{4} == "\x06") { // ASCII string
				$unpackstr = ""; // result string
				$index = 5; // string index

				while($size > 0) { // loop through the entire ASCII string
					$size--;
					$unpackstr .= $result{$index}; // add the string position to return string
					$index++;
				}
				return $unpackstr;
			}
		}
	}
	//if we get to this point, something went wrong;
	$error = true;
	return "ERROR";
}

function getvar($array,$var) {
	if (array_key_exists($var, $array))
		return $array[$var];
	return null;
}

foreach ($servers as $server) {
	$port = $server["port"];
	$addr = $server["address"];
	$lastinfo = (is_array($cache) && count($cache) > $n ? $cache[$n] : array());
	$n++;
	$data = export($addr, $port, '?status');
	if(is_string($data)) {
		//remove pesky null-terminating bytes
		$data = str_replace("\x00", "", $data);
	}
	$variable_value_array = Array();
	if ((!$data || strpos($data, "ERROR") !== false) && (array_key_exists("restarting", $lastinfo))) {
		$variable_value_array['restarting'] = $lastinfo['restarting'] + 1;

	}
	// Split the retrieved data into easily-accessible arrays
	$data_array = explode("&", $data);

	for($i = 0; $i < count($data_array); $i++) {
		//Split the row by the = sign into the identifier at index 0 and the value at index 1 (if the value exists)
		$row = explode("=", $data_array[$i]);
		if(isset($row[1])){
			//All should go here... but just in case.
			$variable_value_array[$row[0]] = $row[1];
		}else{
			$variable_value_array[$row[0]] = null;
		}
	}
	$variable_value_array['cachetime'] = time();
	if (array_key_exists('gamestate', $variable_value_array))
		if ($variable_value_array['gamestate'] == 4)
			$variable_value_array['restarting'] = 1;


	$serverinfo[] = $variable_value_array;

}

echo json_encode($serverinfo);
?>