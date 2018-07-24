<?php
if (isset($_SERVER['REQUEST_URI'])) {
	$URI=explode('/',$_SERVER['REQUEST_URI']);
	if ($URI[1]=="tx") {
		$hash="";
		if (isset($URI[2])) {
			$hash=$URI[2];
		}
	}
}
if (!empty($_COOKIE["network"])) {
	$network=$_COOKIE["network"];
	if ($network=='Mainnet') {
		require_once __DIR__ . '/../tools/include.php';
	} else if ($network=='Testnet') {
		require_once __DIR__ . '/../tools/tinclude.php';
	}
} else {
	setcookie("network","Mainnet",time()+(3600*24*14), "/");
	require_once __DIR__ . '/../tools/include.php';
}
function TrimTrailingZeroes($nbr) {
    return strpos($nbr,'.')!==false ? rtrim(rtrim($nbr,'0'),'.') : $nbr;
}

echo '<div class="container">';
if ($hash!="") {
	$tx=$emercoin->getrawtransaction($hash,1);
	$blockhash = $tx['blockhash'];
	$valuein = 0;
	$numvin = 0;
	$coindaysdestroyed = 0;
	$avgcoindaysdestroyed = 0;
		foreach ($tx['vin'] as $vin) {
			if (isset($vin['txid'])){
				$numvin++;
				$valuein+=getTX_vout_value($emercoin, $vin['txid'], $vin['vout']);
				$timeDiff = bcsub($tx['time'],getTX_vout_time($emercoin, $vin['txid']),0);
				$coindaysdestroyed = bcmul(bcdiv($timeDiff,86400,8),$valuein,6);
			}
		}
	if ($coindaysdestroyed != 0) {
			$avgcoindaysdestroyed = bcdiv($coindaysdestroyed,$valuein,6);
	}
	$valueout = 0;
	$numvout = 0;
	foreach ($tx['vout'] as $vout) {
		if (isset($vout['value'])){
			$numvout++;
			$valueout += $vout['value'];
		}
	}

	$fee = bcsub($valuein,$valueout,6);
	$confirmations = $tx['confirmations'];

	$time=date("Y-m-d G:i:s e",$tx['time']);
	$unixtime=$tx['time'];

	$query = "SELECT blockid, id FROM transactions WHERE txid = '$hash'";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$blockid=$row['blockid'];
		$tx_db_id=$row['id'];
	}

	$block = $emercoin->getblock($blockhash);

	if (isset($block['height'])) {
		$height = $block['height'];
		if ($confirmations<3) {$labelcolor="danger";};
		if ($confirmations>=3 && $confirmations<6) {$labelcolor="warning";};
		if ($confirmations>=6) {$labelcolor="success";};
		echo '
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">'.lang("TRANSACTION_DETAILS").' '.$hash.'</h3>
			</div>
			<div class="panel-body">

				<table class="table">
					<tr><td>'.lang("CONFIRMED_BLOCK").'</td><td width="75%"><a href="/block/'.$blockhash.'" class="btn btn-primary btn-xs" role="button">'.$height.'</a> <span class="label label-'.$labelcolor.'">'.$confirmations.'</span></td></tr>
					<tr><td>'.lang("TIME_TIME").'</td><td>'.$time.'</td><td</tr>
					<tr><td>'.lang("INPUTS_INPUTS").'</td><td><span class="label label-danger">'.TrimTrailingZeroes(number_format($numvin,0)).' / '.TrimTrailingZeroes(number_format($valuein,8)).' EMC</span></td></tr>
					<tr><td>'.lang("OUTPUTS_OUTPUTS").'</td><td><span class="label label-success">'.TrimTrailingZeroes(number_format($numvout,0)).' / '.TrimTrailingZeroes(number_format($valueout,8)).' EMC</span></td></tr>';
					if ($fee<0) {
						echo '<tr><td>'.lang("MINT_MINT").'</td><td>'.($fee*(-1)).' EMC</td></tr>';
					} else {
						echo '<tr><td>'.lang("FEE_FEE").'</td><td>'.$fee.' EMC</td></tr>';
					}
					echo '<tr><td>'.lang("COIN_DESTROYED").'</td><td>'.TrimTrailingZeroes(number_format($coindaysdestroyed,8)).' '.lang("COIN_COIN").'*'.lang("DAYS_DAYS").' / '.$valuein.' EMC = '.TrimTrailingZeroes(number_format($avgcoindaysdestroyed,8)).' '.lang("DAYS_DAYS").'</sub></td></tr>
				</table>

			</div>
		</div>

		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">'.lang("INPUTS_INPUTS").'</h3>
			</div>
			<div class="panel-body">

				<table class="table">
					<tr><th>'.lang("RECEIVED_TX").'</th><th>'.lang("TRACE_TRACE").'</th><th>'.lang("VALUE_VALUE").' [EMC]</th><th>'.lang("ADDRESS_ADDRESS").'</th><th>'.lang("COIN_DAYS").' ['.lang("DAYS_DAYS").']</th></tr>';
					$query_vin = "SELECT id, coinbase, output_txid, address, value, coindaysdestroyed, avgcoindaysdestroyed
							FROM vin
							WHERE blockid='$blockid' AND parenttxid='$tx_db_id'";
					$result_vin = $dbconn->query($query_vin);
					while($row_vin = $result_vin->fetch_assoc())
					{
						$vid=$row_vin['id'];
						$out_tx_id=$row_vin['output_txid'];
						$out_tx_id_short = substr($out_tx_id, 0, 4)."...".substr($out_tx_id, -4);
						$coinbase=$row_vin['coinbase'];
						if ($coinbase!="") {

						}
						$address=$row_vin['address'];
						if ($address=="") {
							$address="N/A";
						}
						else {
							$address='<a href="/address/'.$address.'"><button type="button" class="btn btn-link" style="padding:0">'.$address.'</button></a>';
						}
						$value=$row_vin['value'];
						$coindaysdestroyed=$row_vin['coindaysdestroyed'];
						$avgcoindaysdestroyed=$row_vin['avgcoindaysdestroyed'];
						if ($coinbase=="") {
							echo '<tr><td><a href="/tx/'.$out_tx_id.'" class="btn btn-primary btn-xs" role="button">'.$out_tx_id_short.'</a></td><td>';
							if ($address!="N/A") {
								echo '<a href="/cointrace/received/vin/'.$vid.'" target="_blank"><button type="button" class="btn btn-link" style="padding:0"><i class="fa fa-code-fork fa-rotate-270"></button></a></i>';
							}
							echo '</td><td>'.TrimTrailingZeroes(number_format($value,8)).'</td><td>'.$address.'</td><td>'.TrimTrailingZeroes(number_format($avgcoindaysdestroyed,2)).'</td></tr>';
						} else {
							echo '<tr><td>Coinbase</td><td>'.TrimTrailingZeroes(number_format($value,8)).'</td><td></td><td>N/A</td>td>'.TrimTrailingZeroes(number_format($avgcoindaysdestroyed,2)).'</td></tr>';
						}
					}
				echo '</table>

			</div>
		</div>

		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">'.lang("OUTPUTS_OUTPUTS").'</h3>
			</div>
			<div class="panel-body">

				<table class="table">
					<tr><th>'.lang("SPEND_TX").'</th><th>'.lang("TRACE_TRACE").'</th><th>'.lang("VALUE_VALUE").' [EMC]</th><th>'.lang("ADDRESS_ADDRESS").'</th></tr>';
					$query_vout = "SELECT id, address, value, n
							FROM vout
							WHERE blockid='$blockid' AND parenttxid='$tx_db_id' ORDER BY n";
					$result_vout = $dbconn->query($query_vout);
					while($row_vout = $result_vout->fetch_assoc())
					{
						$vid=$row_vout['id'];
						$address=$row_vout['address'];
						if ($address=="") {
							$address="N/A";
						}
						else {
							$address='<a href="/address/'.$address.'"><button type="button" class="btn btn-link" style="padding:0">'.$address.'</button></a>';
						}
						$value=$row_vout['value'];
						$n=$row_vout['n'];
						$query_vin = "SELECT tx.txid FROM transactions AS tx
									INNER JOIN vin
									ON vin.parenttxid=tx.id AND vin.output_txid='$hash' AND vin.vout='$n'
									WHERE tx.time > $unixtime";

						$result_vin = $dbconn->query($query_vin);
						$spend_tx_id="";
						$spend_tx_id_short="";
						while($row_vin = $result_vin->fetch_assoc())
						{
							$spend_tx_id=$row_vin['txid'];
							$spend_tx_id_short = substr($spend_tx_id, 0, 4)."...".substr($spend_tx_id, -4);
						}

						if ($spend_tx_id!="") {
							echo '<tr><td><a href="/tx/'.$spend_tx_id.'" class="btn btn-primary btn-xs" role="button">'.$spend_tx_id_short.'</a></td>';
						} else {
							echo '<tr><td>'.lang("UNSPEND_UNSPEND").'</td>';
						}
						echo '<td>';
						if ($address!="N/A") {
								echo '<a href="/cointrace/received/vout/'.$vid.'" target="_blank"><button type="button" class="btn btn-link" style="padding:0"><i class="fa fa-code-fork fa-rotate-270"></button></a></i>';
							}
						echo '</td><td>'.TrimTrailingZeroes(number_format($value,8)).'</td><td>'.$address.'</td></tr>';
					}
				echo '</table>

			</div>
		</div>';
	}else {
		echo '<h3>'.lang("UNKNOWN_TRANSACTIONS").'</h3>';
	}
	}else {
		echo '<h3>'.lang("NO_TXPROVIDED").'</h3>';
	}
	echo '</div>';


function getTX_vout_value($emercoin, $txHash, $n) {
	$tx=$emercoin->getrawtransaction($txHash,1);
	$tx_vout=$tx['vout'];
	foreach ($tx_vout as $vout) {
		if ($vout['n'] == $n) {
			return $vout['value'];
		}
	}
}

function getTX_vout_address($emercoin, $txHash, $n) {
	$tx=$emercoin->getrawtransaction($txHash,1);
	$tx_vout=$tx['vout'];
	foreach ($tx_vout as $vout) {
		if ($vout['n'] == $n) {
			return $vout['scriptPubKey']['addresses'];
		}
	}
}

function getTX_vout_time($emercoin, $txHash) {
	$tx=$emercoin->getrawtransaction($txHash,1);
	return $tx['time'];
}
?>
