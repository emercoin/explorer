<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

if (!empty($_COOKIE["lang"])) {
	$lang=$_COOKIE["lang"];
	require("../lang/".$lang.".php");
} else {
	setcookie("lang","en",time()+(3600*24*14), "/");
	require("../lang/en.php");
}

if (isset($_GET['address'])) {
	if (explode('.', $_SERVER['HTTP_HOST'])[0] == "testnet") {
		require_once __DIR__ . '/../../tools/tinclude.php';
	} else {
		require_once __DIR__ . '/../../tools/include.php';
	}
	$difficulty=$emercoin->getdifficulty();
	$difficulty=$difficulty['proof-of-stake'];
	$uriaddress=mysqli_real_escape_string($dbconn, $_GET['address']);

	function TrimTrailingZeroes($nbr) {
		return strpos($nbr,'.')!==false ? rtrim(rtrim($nbr,'0'),'.') : $nbr;
	}

	$query="
		SELECT vout.id, tx.txid, vout.value AS value, tx.time AS time, '' AS vin
		FROM transactions AS tx
		INNER JOIN vout ON vout.parenttxid = tx.id
		WHERE vout.address = '$uriaddress'
		UNION ALL
		SELECT vout.id, tx.txid, vout.value AS value, tx.time AS time, vin.value AS vin
		FROM transactions AS tx
		INNER JOIN vout ON vout.parenttxid = tx.id
		LEFT JOIN vin ON vin.output_txid = tx.txid
		AND vin.vout = vout.n
		WHERE vout.address = '$uriaddress'
		AND vin.address = '$uriaddress'
		";
	$result = $dbconn->query($query);
	$unspend_coins=array();
	while($row = $result->fetch_assoc())
	{
		if ($row['vin']=="") {
			$unspend_coins[$row['id']]['value']=$row['value'];
			$unspend_coins[$row['id']]['time']=$row['time'];
			$unspend_coins[$row['id']]['txid']=$row['txid'];
		} else {
			unset($unspend_coins[$row['id']]);
		}
		//echo $row['id']." ".$row['value']." ".$row['time']." ".$row['vin']."<br>";
	}
	echo '<table class="table">
			<tr><th>'.lang("RECEIVED_TX").'</th><th>'.lang("COINS_COINS").'</th><th>'.lang("AGE_AGE").' ['.lang("DAYS_DAYS").']</th><th>'.lang("AVG_AGE").' [<small><sup>'.lang("DAYS_DAYS").'</sup>/<sub>'.lang("COIN_COIN").'</sub></small>]</th><th>'.lang("MINTING_CHANCE").' <small><sub>'.lang("WITHIN_H").'</sub></small> [%]</th><th>'.lang("ESTIMATED_REWARD").' [EMC]</th>';

	foreach ($unspend_coins as $coins) {
		$value=$coins['value'];
		$time=$coins['time'];
		$currenttime=time();
		$tx_id=$coins['txid'];
		$tx_id_short = substr($tx_id, 0, 4)."...".substr($tx_id, -4);
		$coinage=bcdiv(bcmul($value,bcsub($currenttime,$time,16),16),86400,16);
		$avgcoinage=round(bcdiv($coinage,$value,2),2);
		$avgcoinagelong=bcdiv($coinage,$value,16);
		$visual_chance="";
		$reward="0";
		if ($avgcoinage>30) {
			$reward=bcmul(bcdiv($coinage,365,8),0.06,2);
		}
		$probBlockToday = calculateProbBlockToday($avgcoinagelong, $value, $difficulty);

		echo '<tr><td><a href="/tx/'.$tx_id.'" class="btn btn-primary btn-xs" role="button">'.$tx_id_short.'</a></td><td>'.$value.'</td><td>'.round($coinage,2).'</td><td>'.$avgcoinage.'</td><td class="text-left">'.$probBlockToday.'</td><td>'.$reward.'</tr>';
	}

}

function calculateProbStake ($days, $coins, $difficulty) {
    $prob = 0;
    if ($days > 30) {
		$maxTarget = bcpow(2, 224, 16);
		$target = bcdiv($maxTarget,$difficulty,16);
		$dayWeight = bcsub(min($days,90),30,16);
		$prob = bcdiv(bcmul(bcmul($target,$coins,16),$dayWeight,16),bcpow(2, 256,16),16);
	}
	return $prob;
 };

function calculateProbBlockToday ($days, $coins, $difficulty) {
	$prob = calculateProbStake($days, $coins, $difficulty);
    $res = bcsub(1,pow(bcsub(1,$prob,16), 86400),16);
	$res = bcmul($res,100,2);
	return $res;
};

?>
