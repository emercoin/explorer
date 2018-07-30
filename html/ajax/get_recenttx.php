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


	echo '<div class="panel-heading"><b>'.lang('RECENT_TRANSACTIONS').'</b></div>
	<table class="table">
	<thead>
	<tr><th>'.lang('TX_ID').'</th><th>'.lang('VALUE_EMC').'</th><th></th></tr>
	</thead>
	<tbody>';
	for ($conf = 0;  $conf < 9; $conf++) {
		$block_hash=$emercoin->getblockhash($block_height-$conf);
		$block=$emercoin->getblock($block_hash);
		$flags=$block['flags'];
		if ($flags == "proof-of-stake") {
			$txSkipValue = 2;
		} else {
			$txSkipValue = 1;
		}
		$txs=$block['tx'];
		$block_numtx = 0;
		foreach ($txs as $tx) {
			$tx_full=$emercoin->getrawtransaction($tx,1);
			$block_numtx++;
			$vout = 0;
			$confirmations = $tx_full['confirmations'];
			foreach ($tx_full['vin'] as $vin) {
				if (isset($vin['txid'])){
					if ($block_numtx > $txSkipValue) {
						$vout += getTX_vout_value($emercoin, $vin['txid'], $vin['vout']);
					}
				}
			}
			if ($block_numtx > $txSkipValue) {
				$tx_id_short = substr($tx, 0, 4)."...".substr($tx, -4);
				if ($confirmations<3) {$labelcolor="danger";};
				if ($confirmations>=3 && $confirmations<6) {$labelcolor="warning";};
				if ($confirmations>=6) {$labelcolor="success";};
				echo '<tr><td><a href="/tx/'.$tx.'" class="btn btn-primary btn-xs" role="button">'.$tx_id_short.'</a></td><td>'.$vout.'</td><td><span class="label label-'.$labelcolor.'">'.$confirmations.'</span></td></tr>';
			}
		}
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
