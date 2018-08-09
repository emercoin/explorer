<?php
if (!empty($_COOKIE["lang"])) {
	$lang=$_COOKIE["lang"];
	require("../lang/".$lang.".php");
} else {
	setcookie("lang","en",time()+(3600*24*14), "/");
	require("../lang/en.php");
}

	function timeAgo ($time) {
		$time = time() - $time;

		$tokens = array (
			86400 => 'day',
			3600 => 'hour',
			60 => 'minute',
			1 => 'second'
		);

		foreach ($tokens as $unit => $text) {
			if ($time < $unit) continue;
			$numberOfUnits = floor($time / $unit);
			return $numberOfUnits.' '.$text.(($numberOfUnits>1)?'s':'');
		}
	}

	if (explode('.', $_SERVER['HTTP_HOST'])[0] == "testnet") {
		require_once __DIR__ . '/../../tools/tinclude.php';
	} else {
		require_once __DIR__ . '/../../tools/include.php';
	}

$emc_info=$emercoin->getinfo();
$block_height=$emc_info['blocks'];
$connections=$emc_info['connections'];

$query = "SELECT height FROM blocks
			ORDER BY height DESC LIMIT 1";
$result = $dbconn->query($query);
while($row = $result->fetch_assoc()) {
	$dbHeight = $row['height'];
}	

echo '<div class="panel-heading"><b>Explorer Status</b></div>
<div class="panel-body">';
if ($block_height == $dbHeight) {
	echo '<span class="label label-success">SYNCHRONOUS</span><br>';
} else {
	echo '<span class="label label-danger">OUT-OF-SYNC</span><br>';
}
echo 'Connections: '.$connections;
echo '</div>';

	
?>