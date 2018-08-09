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

$txSkipValue = 1;
$block_numtx = 0;

$emc_info=$emercoin->getinfo();
$block_height=$emc_info['blocks'];


	echo '<div class="panel-heading"><b>Recent Blocks</b></div>
	<table class="table">
	<thead>
	<tr><th>#</th><th>'.lang('TIME_AGO').'</th><th>'.lang('TX_COUNT').'</th><th>'.lang('TX_VOLUME_EMC').'</th><th></th></tr>
	</thead>
	<tbody>';
	for ($conf = 0;  $conf < 5; $conf++) {
		$block_hash=$emercoin->getblockhash($block_height-$conf);
		$block=$emercoin->getblock($block_hash);
		$height = $block['height'];
		$block_time = $block['time'];
		$flags=$block['flags'];
		if ($flags == "proof-of-stake") {
			$txSkipValue = 2;
		} else {
			$txSkipValue = 1;
		}
		$txs=$block['tx'];
		$block_numtx = 0;
		$voutValue = 0;
		foreach ($txs as $tx) {
			$tx_full=$emercoin->getrawtransaction($tx,1);
			$block_numtx++;
			foreach ($tx_full['vin'] as $vin) {
				if (isset($vin['txid'])){
					if ($block_numtx > $txSkipValue) {
						$voutValue += getTX_vout_value($emercoin, $vin['txid'], $vin['vout']);
					}
				}
			}
		}
		$block_read_time=date("Y-m-d G:i:s",$block_time);
		echo '<tr><td><a href="/block/'.$block_hash.'" class="btn btn-primary btn-xs" role="button">'.$height.'</a></td><td><abbr title="'.$block_read_time.'">'.timeAgo($block_time).'</abbr></td><td>'.($block_numtx-$txSkipValue).'</td><td>'.$voutValue.'</td></tr>';
	}
	echo "</tbody></table>";


function getTX_vout_value($emercoin, $txHash, $n) {
	$tx=$emercoin->getrawtransaction($txHash,1);
	$tx_vout=$tx['vout'];
	foreach ($tx_vout as $vout) {
		if ($vout['n'] == $n) {
			return $vout['value'];
		}
	}
}
?>
