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
	$query = "SELECT hash, height, size, previousblockhash, time, flags, difficulty, total_coins, total_avgcoindays, nonce, merkleroot, numtx, numvin, numvout, valuein, valueout, mint, fee, coindaysdestroyed, avgcoindaysdestroyed
		FROM blocks
		WHERE id = '$block_id'";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$hash=$row['hash'];
		$prev_hash=$row['previousblockhash'];
		$height=$row['height'];
		$next_height=($height+1);
		$prev_hash_short = substr($prev_hash, 0, 4)."...".substr($prev_hash, -4);
		$time=date("Y-m-d G:i:s e",$row['time']);
		$flag=$row['flags'];
		$nonce=$row['nonce'];
		$merkleroot=$row['merkleroot'];
		$difficulty=$row['difficulty'];
		$numtx=$row['numtx'];
		$numvin=$row['numvin'];
		$numvout=$row['numvout'];
		$valuein=$row['valuein'];
		$valueout=$row['valueout'];
		$total_coins=$row['total_coins'];
		$mint=$row['mint'];
		$fee=bcsub(bcsub($valueout,$mint,8),$valuein,8);
		$size=$row['size'];
		$total_avgcoindays=$row['total_avgcoindays'];
		$coindaysdestroyed=$row['coindaysdestroyed'];
		$avgcoindaysdestroyed=$row['avgcoindaysdestroyed'];
		if (strpos($flag,'proof-of-work') !== false) {
			$flag="PoW";
			$flagcolor="danger";
			$feeWOmint=bcmul($fee,-1,8);
		} else {
			$flag="PoS";
			$flagcolor="success";
			$feeWOmint=bcmul($fee,-1,8);
		}
	}
	if (isset($height)) {

		$query = "SELECT hash FROM blocks WHERE height = '$next_height'";
		$result = $dbconn->query($query);
		while($row = $result->fetch_assoc())
		{
			$next_hash=$row['hash'];
			$next_hash_short = substr($next_hash, 0, 4)."...".substr($next_hash, -4);
		}


		echo '
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">'.lang("BLOCK_DETAILS").' - #'.$height.'</h3>
			</div>
			<div class="panel-body">

				<table class="table">';
				if (isset($next_hash)) {
					echo '<tr><td>'.lang("HASH_HASH").'</td><td><a href="/block/'.$prev_hash.'" class="btn btn-primary btn-xs" role="button"><i class="fa fa-arrow-left"></i> '.$prev_hash_short.'</a> '.$hash.' <a href="/block/'.$next_hash.'" class="btn btn-primary btn-xs" role="button">'.$next_hash_short.' <i class="fa fa-arrow-right"></i></a></td>';
				} else {
					echo '<tr><td>'.lang("HASH_HASH").'</td><td><a href="/block/'.$prev_hash.'" class="btn btn-primary btn-xs" role="button"><i class="fa fa-arrow-left"></i> '.$prev_hash_short.'</a> '.$hash.'</a></td>';
				}

				echo '
				<tr><td>'.lang("TIME_TIME").'</td><td>'.$time.'</td></tr>
				<tr><td class="text-'.$flagcolor.'">'.$flag.'</span> '.lang("DIFFICULTY_DIFFICULTY").'</td><td>'.TrimTrailingZeroes(number_format($difficulty,8)).'</td></tr>
				<tr><td>'.lang("COINS_AVAILABLE").'</td><td>'.TrimTrailingZeroes(number_format($total_coins,8)).' EMC</td></tr>
				<tr><td>'.lang("AVG_AGE").'</td><td>'.TrimTrailingZeroes(number_format($total_avgcoindays,8)).' '.lang("DAYS_DAYS").'</td></tr>
				<tr><td>'.lang("NONCE_NONCE").'</td><td>'.$nonce.'</td></tr>
				<tr><td>'.lang("MERKLE_ROOT").'</td><td>'.$merkleroot.'</td></tr>
				<tr><td>'.lang("TRANSACTIONS_TRANSACTIONS").'</td><td>'.$numtx.'</td></tr>
				<tr><td>'.lang("INPUTS_INPUTS").'</td><td><span class="label label-danger">'.$numvin.' / '.$valuein.' EMC</span></td></tr>
				<tr><td>'.lang("OUTPUTS_OUTPUTS").'</td><td><span class="label label-success">'.$numvout.' / '.$valueout.' EMC</span></td></tr>
				<tr><td>'.lang("MINT_MINT").'</td><td><span class="label label-primary">'.$mint.' EMC</span></td></tr>
				<tr><td>'.lang("SIZE_SIZE").'</td><td>'.TrimTrailingZeroes(number_format($size,2)).' kiB</td></tr>
				<tr><td>'.lang("FEE_FEE").'</td><td>'.TrimTrailingZeroes(number_format($feeWOmint,8)).' EMC</td></tr>
				<tr><td>'.lang("COIN_DESTROYED").'</td><td>'.TrimTrailingZeroes(number_format($coindaysdestroyed,8)).' '.lang("COIN_COIN").'*'.lang("DAYS_DAYS").' / '.$valuein.' EMC = '.TrimTrailingZeroes(number_format($avgcoindaysdestroyed,8)).' '.lang("DAYS_DAYS").'</sub></td></tr>
				</table>


			</div>
		</div>
		';

	} else {
		echo '<h3>'.lang("UNKNOWN_BLOCK").'</h3>';
	}
} else {
	echo '<h3>No Block Provided</h3>';
}
?>
