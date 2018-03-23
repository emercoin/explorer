	<?php
	error_reporting(E_ALL);
	ini_set("display_errors", 1);
if (!empty($_COOKIE["network"])) {
	$network=$_COOKIE["network"];
	if ($network=='Mainnet') {
		require_once __DIR__ . '/../../../tools/include.php';
	} else if ($network=='Testnet') {
		require_once __DIR__ . '/../../../tools/tinclude.php';
	}
} else {
	setcookie("network","Mainnet",time()+(3600*24*14), "/");
	require_once __DIR__ . '/../../../tools/include.php';
}
if (!empty($_COOKIE["lang"])) {
	$lang=$_COOKIE["lang"];
	require("../../lang/".$lang.".php");
} else {
	setcookie("lang","en",time()+(3600*24*14), "/");
	require("../../lang/en.php");
}

function TrimTrailingZeroes($nbr) {
    return strpos($nbr,'.')!==false ? rtrim(rtrim($nbr,'0'),'.') : $nbr;
}

$block_id=mysqli_real_escape_string($dbconn, $_GET['block_id']);

if (isset($block_id) && $block_id!="") {
	echo '
	<div class="panel panel-default">
		<div class="panel-heading">
			<h3 class="panel-title">'.lang("TRANSACTIONS_TRANSACTIONS").'</h3>
		</div>
		<div class="panel-body">
	<table class="table table-striped">
	<thead>
	<tr><th>'.lang("TX_ID").'</th><th>'.lang("FEE_FEE").'</th><th>'.lang("INPUTS_INPUTS").'</th><th>'.lang("OUTPUTS_OUTPUTS").'</th></tr>
	</thead>
	<tbody>';
	$query="SELECT vin.id+'' AS vid, tx.id, tx.txid, tx.time, tx.fee, vin.coinbase, vin.value AS sent, vin.coindaysdestroyed, vin.avgcoindaysdestroyed, '' AS received, vin.address
	FROM transactions AS tx
	INNER JOIN vin ON vin.parenttxid = tx.id
	WHERE tx.blockid = '$block_id'
	UNION ALL
	SELECT vout.id+'' AS vid, tx.id, tx.txid, tx.time, tx.fee, '' AS coinbase, '' AS sent, '', '', vout.value AS received, vout.address
	FROM transactions AS tx
	INNER JOIN vout ON vout.parenttxid = tx.id
	WHERE tx.blockid = '$block_id'
	ORDER BY id";
	$countvin=0;
	$countvout=0;
	$input="";
	$output="";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$tx_id=$row['txid'];
		if(!isset($oldid)) {
			$oldid=$row['txid'];
			$tx_id=$oldid;
		}
		if ($oldid!=$tx_id) {
			$tx_id_short = substr($oldid, 0, 4)."...".substr($oldid, -4);
			echo '<tr><td><a href="/tx/'.$oldid.'" class="btn btn-primary btn-xs" role="button">'.$tx_id_short.'</a></td><td>'.TrimTrailingZeroes(number_format($fee,8)).'</td><td>'.$input.'</td><td>'.$output.'</td></tr>';
			$input="";
			$output="";
			$countvin=0;
			$countvout=0;
			$oldid=$tx_id;
		}

		if ($row['sent']!="") {
			$vid=$row['vid'];
			if ($countvin>0) {
				$input.='<hr>';
			}
			if (($row['coinbase'])!="") {
				$input.='coinbase<br>0 EMC</td>';
			} else {
				if ($row['address']=="") {
					$address="N/A";
				} else {
					$address=$row['address'];
				}
				$input.='<a href="/address/'.$address.'"><button type="button" class="btn btn-link" style="padding:0">'.$address.'</button></a><br>';
				if ($address!="N/A") {
					$input.='<a href="/cointrace/received/vin/'.$vid.'" target="_blank"><button type="button" class="btn btn-link" style="padding:0"><i class="fa fa-code-fork fa-rotate-270"></button></a></i>';
				}
				$input.=' <span class="label label-danger">'.TrimTrailingZeroes(number_format($row['sent'],8)).' EMC</span> <sub>'.TrimTrailingZeroes(number_format($row['avgcoindaysdestroyed'],2)).' Days</sub><br>';
				$countvin++;
			}
		}
		if ($row['received']!="") {
			$vid=$row['vid'];
			if ($countvout>0) {
				$output.='<hr>';
			}
			if ($row['address']=="") {
				$address="N/A";
			} else {
				$address=$row['address'];
			}
			$output.='<a href="/address/'.$address.'"><button type="button" class="btn btn-link" style="padding:0">'.$address.'</button></a><br>';
			if ($address!="N/A") {
				$output.='<a href="/cointrace/received/vout/'.$vid.'" target="_blank"><button type="button" class="btn btn-link" style="padding:0"><i class="fa fa-code-fork fa-rotate-270"></button></a></i>';
			}
			$output.=' <span class="label label-success">'.TrimTrailingZeroes(number_format($row['received'],8)).' EMC</span>';
			$countvout++;
		}
		$fee=$row['fee'];
	}
	if (isset($tx_id)) {
		$tx_id_short = substr($tx_id, 0, 4)."...".substr($tx_id, -4);
		echo '<tr><td><a href="/tx/'.$tx_id.'" class="btn btn-primary btn-xs" role="button">'.$tx_id_short.'</a></td><td>'.TrimTrailingZeroes(number_format($fee,8)).'</td><td>'.$input.'</td><td>'.$output.'</td></tr>';
	}
	echo '</tbody>';
	echo '</table>
			</div>
		</div>';
}
?>
