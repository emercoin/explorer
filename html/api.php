<?php
error_reporting(0);
ini_set("display_errors", 0);

$type="";
$subtype="";
$pattern="";
$details="reduce";
if (isset($_SERVER['REQUEST_URI'])) {
	$URI=explode('/',$_SERVER['REQUEST_URI']);
	if ($URI[1]=="api") {
		if (isset($URI[2])) {
			$type=urldecode($URI[2]);
			if (isset($URI[3])) {
				$subtype=urldecode($URI[3]);
				if (isset($URI[4])) {
					$pattern=urldecode($URI[4]);
					if (isset($URI[5])) {
						$details=urldecode($URI[5]);
					}	
				}
			}	
		}	
	}
}

$valid_access=0;

if ($type=="help")
{
	header('Content-Type: application/json');
	$commands=array(
		'/api/block/hash/<hash>/less' => 'details about a given block',
		'/api/block/hash/<hash>' => 'details about a given block including it\'s transactions',
		'/api/block/hash/<hash>/full' => 'details about a given block including it\'s transactions together with all transaction in- and outputs',
		'/api/block/height/<height>/less' => 'details about a given block (block height as reference)',
		'/api/block/height/<height>' => 'details about a given block including it\'s transactions (block height as reference)',
		'/api/block/height/<height>/full' => 'details about a given block including it\'s transactions together with all transaction in- and outputs (block height as reference)',
		'/api/block/latest/less' => 'details about the last block',
		'/api/block/latest' => 'details about the last block including it\'s transactions',
		'/api/block/latest/full' => 'details about the last block including it\'s transactions together with all transaction in- and outputs',
		'/api/tx/hash/<hash>/less' => 'details about a given transaction',
		'/api/tx/hash/<hash>' => 'details about a given transaction including it\'s in- and outputs',
		'/api/tx/hash/<hash>/full' => 'more details information about a given transaction including it\'s in- and outputs',
		'/api/address/balance/<address>/full' => 'details about a given address including an transaction overview',
		'/api/address/balance/<address>' => 'details about a given address',
		'/api/address/balance/<address>/less' => 'shows the balance only',
		'/api/address/isvalid/<address>' => 'returns 1 if the address is valid',
		'/api/stats/block_height' => 'returns the current block height',
		'/api/stats/coin_supply' => 'returns the current coin supply',
	);
	$help=array(
		'version' => '1.2.2',
		'date' => '2017-03-14',
		'commands' => $commands
	);
	echo json_encode($help, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	exit;
}
if ($type=="block" && $pattern!="" && $details=="full" || $type=="block" && $subtype=="latest" && $pattern=="full") 
{
	if ($subtype=="hash") {
		$valid_access=1;
		header('Content-Type: application/json');
		$query="SELECT * FROM blocks WHERE hash = '$pattern'";
	} else if ($subtype=="height") {
		$valid_access=1;
		header('Content-Type: application/json');
		$query="SELECT * FROM blocks WHERE height = '$pattern'";
	} else if ($subtype=="latest") {
		$valid_access=1;
		header('Content-Type: application/json');
		$query="SELECT * FROM blocks ORDER BY height DESC LIMIT 1";
	} else {
		echo json_encode(array('error' => 'Unknown API call'), JSON_PRETTY_PRINT); exit;
	}
	$result = mysqli_query($dbconn,$query);
	while($row = $result->fetch_assoc())
	{
		$blockid=$row['id'];
		$blockhash=$row['hash'];
		$size=$row['size'];
		$height=$row['height'];
		$version=$row['version'];
		$merkleroot=$row['merkleroot'];
		$time=$row['time'];
		$nonce=$row['nonce'];
		$bits=$row['bits'];
		$difficulty=$row['difficulty'];
		$mint=$row['mint'];
		$previousblockhash=$row['previousblockhash'];
		$flags=$row['flags'];
		$proofhash=$row['proofhash'];
		$entropybit=$row['entropybit'];
		$modifier=$row['modifier'];
		$modifierchecksum=$row['modifierchecksum'];
		$numtx=$row['numtx'];
		$numvin=$row['numvin'];
		$numvout=$row['numvout'];
		$valuein=$row['valuein'];
		$valueout=$row['valueout'];
		$fee=$row['fee'];
		$total_coins=$row['total_coins'];
		$coindaysdestroyed=$row['coindaysdestroyed'];
		$avgcoindaysdestroyed=$row['avgcoindaysdestroyed'];
		$total_coindays=$row['total_coindays'];
		$total_avgcoindays=$row['total_avgcoindays'];
		$total_addresses_used=$row['total_addresses_used'];
		$total_addresses_unused=$row['total_addresses_unused'];		
	}
	if (isset($blockhash)) {
	$next_height=($height+1);
	$query="SELECT hash FROM blocks WHERE height = '$next_height'";
	$result = mysqli_query($dbconn,$query);
	$nextblockhash="";
	while($row = $result->fetch_assoc())
	{
		$nextblockhash=$row['hash'];
	}
	
	$query="SELECT * FROM transactions WHERE blockid = '$blockid'";
	$result = mysqli_query($dbconn,$query);
	$tx_array=array();
	while($row = $result->fetch_assoc())
	{
		$txid=$row['id'];
		$txhash=$row['txid'];
		$time=$row['time'];
		$numvin=$row['numvin'];
		$numvout=$row['numvout'];
		$valuein=$row['valuein'];
		$valueout=$row['valueout'];
		$fee=$row['fee'];
		$coindaysdestroyed=$row['coindaysdestroyed'];
		$avgcoindaysdestroyed=$row['avgcoindaysdestroyed'];		

		$query="SELECT * FROM vin WHERE parenttxid = '$txid'";
		$vinresult = mysqli_query($dbconn,$query);
		$vin_array=array();
		while($vinrow = $vinresult->fetch_assoc())
		{
			$output_txid=$vinrow['output_txid'];
			$coinbase=$vinrow['coinbase'];
			$vout=$vinrow['vout'];
			$asm=$vinrow['asm'];
			$hex=$vinrow['hex'];
			$sequence=$vinrow['sequence'];
			$address=$vinrow['address'];
			$value=$vinrow['value'];
			$coindaysdestroyed=$vinrow['coindaysdestroyed'];
			$avgcoindaysdestroyed=$vinrow['avgcoindaysdestroyed'];
			$vin=array(
				'output_txid' => $output_txid,
				'coinbase' => $coinbase,
				'vout' => $vout,
				'asm' => $asm,
				'hex' => $hex,
				'sequence' => $sequence,
				'address' => $address,
				'value' => $value,
				'coindaysdestroyed' => $coindaysdestroyed,
				'avgcoindaysdestroyed' => $avgcoindaysdestroyed
			);
			array_push($vin_array, $vin);
		}
		
		$query="SELECT * FROM vout WHERE parenttxid = '$txid'";
		$voutresult = mysqli_query($dbconn,$query);
		$vout_array=array();
		while($voutrow = $voutresult->fetch_assoc())
		{
			$value=$voutrow['value'];
			$n=$voutrow['n'];
			$asm=$voutrow['asm'];
			$hex=$voutrow['hex'];
			$reqsigs=$voutrow['reqsigs'];
			$type=$voutrow['type'];
			$address=$voutrow['address'];
			$vout=array(
				'value' => $value,
				'n' => $n,
				'asm' => $asm,
				'hex' => $hex,
				'reqsigs' => $reqsigs,
				'type' => $type,
				'address' => $address
			);
			array_push($vout_array, $vout);
		}
		
		$tx=array(
			'tx_hash' => $txhash,
			'time' => $time,
			'numvin' => $numvin,
			'numvout' => $numvout,
			'valuein' => $valuein,
			'valueout' => $valueout,
			'fee' => $fee,
			'coindaysdestroyed' => $coindaysdestroyed,
			'avgcoindaysdestroyed' => $avgcoindaysdestroyed,
			'vin' => $vin_array,
			'vout' => $vout_array
		);
		array_push($tx_array, $tx);
	}
			
		$block_array=array(
			'blockhash' => $blockhash,
			'size' => $size,
			'height' => $height,
			'version' => $version,
			'merkleroot' => $merkleroot,
			'time' => $time,
			'nonce' => $nonce,
			'bits' => $bits,
			'difficulty' => $difficulty,
			'mint' => $mint,
			'previousblockhash' => $previousblockhash,
			'nextblockhash' => $nextblockhash,
			'flags' => $flags,
			'proofhash' => $proofhash,
			'entropybit' => $entropybit,
			'modifier' => $modifier,
			'modifierchecksum' => $modifierchecksum,
			'numtx' => $numtx,
			'numvin' => $numvin,
			'numvout' => $numvout,
			'valuein' => $valuein,
			'valueout' => $valueout,
			'fee' => $fee,
			'total_coins' => $total_coins,
			'coindaysdestroyed' => $coindaysdestroyed,
			'avgcoindaysdestroyed' => $avgcoindaysdestroyed,
			'total_coindays' => $total_coindays,
			'total_avgcoindays' => $total_avgcoindays,
			'total_addresses_used' => $total_addresses_used,
			'total_addresses_empty' => $total_addresses_unused,
			'transactions' => $tx_array
		);
		
		
		echo json_encode($block_array, JSON_PRETTY_PRINT);
	} else {
		echo $blockhash;
		echo json_encode(array('error' => 'Could not decode hash'), JSON_PRETTY_PRINT);
	}	
}	

if ($type=="block" && $pattern!="" && $details=="reduce" || $type=="block" && $subtype=="latest" && $details=="reduce")
{
	if ($subtype=="hash") {
		$valid_access=1;
		header('Content-Type: application/json');
		$query="SELECT * FROM blocks WHERE hash = '$pattern'";
	} else if ($subtype=="height") {
		$valid_access=1;
		header('Content-Type: application/json');
		$query="SELECT * FROM blocks WHERE height = '$pattern'";
	}  else if ($subtype=="latest") {
		$valid_access=1;
		header('Content-Type: application/json');
		$query="SELECT * FROM blocks ORDER BY height DESC LIMIT 1";
	} else {
		echo json_encode(array('error' => 'Unknown API call'), JSON_PRETTY_PRINT); exit;
	}
	$result = mysqli_query($dbconn,$query);
	while($row = $result->fetch_assoc())
	{
		$blockid=$row['id'];
		$blockhash=$row['hash'];
		$size=$row['size'];
		$height=$row['height'];
		$version=$row['version'];
		$merkleroot=$row['merkleroot'];
		$time=$row['time'];
		$nonce=$row['nonce'];
		$bits=$row['bits'];
		$difficulty=$row['difficulty'];
		$mint=$row['mint'];
		$previousblockhash=$row['previousblockhash'];
		$flags=$row['flags'];
		$proofhash=$row['proofhash'];
		$entropybit=$row['entropybit'];
		$modifier=$row['modifier'];
		$modifierchecksum=$row['modifierchecksum'];
		$numtx=$row['numtx'];
		$numvin=$row['numvin'];
		$numvout=$row['numvout'];
		$valuein=$row['valuein'];
		$valueout=$row['valueout'];
		$fee=$row['fee'];
		$total_coins=$row['total_coins'];
		$coindaysdestroyed=$row['coindaysdestroyed'];
		$avgcoindaysdestroyed=$row['avgcoindaysdestroyed'];
		$total_coindays=$row['total_coindays'];
		$total_avgcoindays=$row['total_avgcoindays'];
		$total_addresses_used=$row['total_addresses_used'];
		$total_addresses_unused=$row['total_addresses_unused'];		
	}
	if (isset($blockhash)) {
		$next_height=($height+1);
		$query="SELECT hash FROM blocks WHERE height = '$next_height'";
		$result = mysqli_query($dbconn,$query);
		$nextblockhash="";
		while($row2 = $result->fetch_assoc())
		{
			$nextblockhash=$row['hash'];
		}
		
		$query="SELECT * FROM transactions WHERE blockid = '$blockid'";
		$result = mysqli_query($dbconn,$query);
		$tx_array=array();
		while($row = $result->fetch_assoc())
		{
			$txid=$row['id'];
			$txhash=$row['txid'];
			$time=$row['time'];
			$numvin=$row['numvin'];
			$numvout=$row['numvout'];
			$valuein=$row['valuein'];
			$valueout=$row['valueout'];
			$fee=$row['fee'];
			$coindaysdestroyed=$row['coindaysdestroyed'];
			$avgcoindaysdestroyed=$row['avgcoindaysdestroyed'];		
			$tx=array(
				'tx_hash' => $txhash,
				'time' => $time,
				'numvin' => $numvin,
				'numvout' => $numvout,
				'valuein' => $valuein,
				'valueout' => $valueout,
				'fee' => $fee,
				'coindaysdestroyed' => $coindaysdestroyed,
				'avgcoindaysdestroyed' => $avgcoindaysdestroyed
			);
			array_push($tx_array, $tx);
		}
			
		$block_array=array(
			'blockhash' => $blockhash,
			'size' => $size,
			'height' => $height,
			'version' => $version,
			'merkleroot' => $merkleroot,
			'time' => $time,
			'nonce' => $nonce,
			'bits' => $bits,
			'difficulty' => $difficulty,
			'mint' => $mint,
			'previousblockhash' => $previousblockhash,
			'nextblockhash' => $nextblockhash,
			'flags' => $flags,
			'proofhash' => $proofhash,
			'entropybit' => $entropybit,
			'modifier' => $modifier,
			'modifierchecksum' => $modifierchecksum,
			'numtx' => $numtx,
			'numvin' => $numvin,
			'numvout' => $numvout,
			'valuein' => $valuein,
			'valueout' => $valueout,
			'fee' => $fee,
			'total_coins' => $total_coins,
			'coindaysdestroyed' => $coindaysdestroyed,
			'avgcoindaysdestroyed' => $avgcoindaysdestroyed,
			'total_coindays' => $total_coindays,
			'total_avgcoindays' => $total_avgcoindays,
			'total_addresses_used' => $total_addresses_used,
			'total_addresses_empty' => $total_addresses_unused,
			'transactions' => $tx_array
		);
		
		echo json_encode($block_array, JSON_PRETTY_PRINT);
	} else {
		echo json_encode(array('error' => 'Could not decode hash'), JSON_PRETTY_PRINT);
	}	
}	

if ($type=="block" && $pattern!="" && $details=="less" || $type=="block" && $subtype=="latest" && $pattern=="less")
{
	if ($subtype=="hash") {
		$valid_access=1;
		header('Content-Type: application/json');
		$query="SELECT * FROM blocks WHERE hash = '$pattern'";
	} else if ($subtype=="height") {
		$valid_access=1;
		header('Content-Type: application/json');
		$query="SELECT * FROM blocks WHERE height = '$pattern'";
	} else if ($subtype=="latest") {
		$valid_access=1;
		header('Content-Type: application/json');
		$query="SELECT * FROM blocks ORDER BY height DESC LIMIT 1";
	} else {
		echo json_encode(array('error' => 'Unknown API call'), JSON_PRETTY_PRINT); exit;
	}
	$result = mysqli_query($dbconn,$query);
	while($row = $result->fetch_assoc())
	{
		$blockid=$row['id'];
		$blockhash=$row['hash'];
		$size=$row['size'];
		$height=$row['height'];
		$version=$row['version'];
		$merkleroot=$row['merkleroot'];
		$time=$row['time'];
		$nonce=$row['nonce'];
		$bits=$row['bits'];
		$difficulty=$row['difficulty'];
		$mint=$row['mint'];
		$previousblockhash=$row['previousblockhash'];
		$flags=$row['flags'];
		$proofhash=$row['proofhash'];
		$entropybit=$row['entropybit'];
		$modifier=$row['modifier'];
		$modifierchecksum=$row['modifierchecksum'];
		$numtx=$row['numtx'];
		$numvin=$row['numvin'];
		$numvout=$row['numvout'];
		$valuein=$row['valuein'];
		$valueout=$row['valueout'];
		$fee=$row['fee'];
		$total_coins=$row['total_coins'];
		$coindaysdestroyed=$row['coindaysdestroyed'];
		$avgcoindaysdestroyed=$row['avgcoindaysdestroyed'];
		$total_coindays=$row['total_coindays'];
		$total_avgcoindays=$row['total_avgcoindays'];
		$total_addresses_used=$row['total_addresses_used'];
		$total_addresses_unused=$row['total_addresses_unused'];		
	}
	if (isset($blockhash)) {
		$next_height=($height+1);
		$query="SELECT hash FROM blocks WHERE height = '$next_height'";
		$result = mysqli_query($dbconn,$query);
		$nextblockhash="";
		while($row2 = $result->fetch_assoc())
		{
			$nextblockhash=$row['hash'];
		}
		$block_array=array(
			'blockhash' => $blockhash,
			'size' => $size,
			'height' => $height,
			'version' => $version,
			'merkleroot' => $merkleroot,
			'time' => $time,
			'nonce' => $nonce,
			'bits' => $bits,
			'difficulty' => $difficulty,
			'mint' => $mint,
			'previousblockhash' => $previousblockhash,
			'nextblockhash' => $nextblockhash,
			'flags' => $flags,
			'proofhash' => $proofhash,
			'entropybit' => $entropybit,
			'modifier' => $modifier,
			'modifierchecksum' => $modifierchecksum,
			'numtx' => $numtx,
			'numvin' => $numvin,
			'numvout' => $numvout,
			'valuein' => $valuein,
			'valueout' => $valueout,
			'fee' => $fee,
			'total_coins' => $total_coins,
			'coindaysdestroyed' => $coindaysdestroyed,
			'avgcoindaysdestroyed' => $avgcoindaysdestroyed,
			'total_coindays' => $total_coindays,
			'total_avgcoindays' => $total_avgcoindays,
			'total_addresses_used' => $total_addresses_used,
			'total_addresses_empty' => $total_addresses_unused
		);
		
		echo json_encode($block_array, JSON_PRETTY_PRINT);
	} else {
		echo json_encode(array('error' => 'Could not decode hash'), JSON_PRETTY_PRINT);
	}	
}	



if ($type=="tx" && $pattern!="" && $details=="full")
{
	if ($subtype=="hash") {
		$valid_access=1;
		header('Content-Type: application/json');
		$query="SELECT * FROM transactions WHERE txid = '$pattern'";
	} else {
		echo json_encode(array('error' => 'Unknown API call'), JSON_PRETTY_PRINT); exit;
	}
	$result = mysqli_query($dbconn,$query);
	$tx_array=array();
	while($row = $result->fetch_assoc())
	{
		$txid=$row['id'];
		$txhash=$row['txid'];
		$time=$row['time'];
		$numvin=$row['numvin'];
		$numvout=$row['numvout'];
		$valuein=$row['valuein'];
		$valueout=$row['valueout'];
		$fee=$row['fee'];
		$coindaysdestroyed=$row['coindaysdestroyed'];
		$avgcoindaysdestroyed=$row['avgcoindaysdestroyed'];		

		$query="SELECT * FROM vin WHERE parenttxid = '$txid'";
		$vinresult = mysqli_query($dbconn,$query);
		$vin_array=array();
		
		if (isset($txid)) {
		
			while($vinrow = $vinresult->fetch_assoc())
			{
				$output_txid=$vinrow['output_txid'];
				$coinbase=$vinrow['coinbase'];
				$vout=$vinrow['vout'];
				$asm=$vinrow['asm'];
				$hex=$vinrow['hex'];
				$sequence=$vinrow['sequence'];
				$address=$vinrow['address'];
				$value=$vinrow['value'];
				$coindaysdestroyed=$vinrow['coindaysdestroyed'];
				$avgcoindaysdestroyed=$vinrow['avgcoindaysdestroyed'];
				$vin=array(
					'output_txid' => $output_txid,
					'coinbase' => $coinbase,
					'vout' => $vout,
					'asm' => $asm,
					'hex' => $hex,
					'sequence' => $sequence,
					'address' => $address,
					'value' => $value,
					'coindaysdestroyed' => $coindaysdestroyed,
					'avgcoindaysdestroyed' => $avgcoindaysdestroyed
				);
				array_push($vin_array, $vin);
			}
			
			$query="SELECT * FROM vout WHERE parenttxid = '$txid'";
			$voutresult = mysqli_query($dbconn,$query);
			$vout_array=array();
			while($voutrow = $voutresult->fetch_assoc())
			{
				$value=$voutrow['value'];
				$n=$voutrow['n'];
				$asm=$voutrow['asm'];
				$hex=$voutrow['hex'];
				$reqsigs=$voutrow['reqsigs'];
				$type=$voutrow['type'];
				$address=$voutrow['address'];
				$vout=array(
					'value' => $value,
					'n' => $n,
					'asm' => $asm,
					'hex' => $hex,
					'reqsigs' => $reqsigs,
					'type' => $type,
					'address' => $address
				);
				array_push($vout_array, $vout);
			}
		
			$tx_array=array(
				'tx_hash' => $txhash,
				'time' => $time,
				'numvin' => $numvin,
				'numvout' => $numvout,
				'valuein' => $valuein,
				'valueout' => $valueout,
				'fee' => $fee,
				'coindaysdestroyed' => $coindaysdestroyed,
				'avgcoindaysdestroyed' => $avgcoindaysdestroyed,
				'vin' => $vin_array,
				'vout' => $vout_array
			);
		}	
	}

	if (isset($txid)) {	
		echo json_encode($tx_array, JSON_PRETTY_PRINT);
	} else {
		echo json_encode(array('error' => 'Could not decode hash'), JSON_PRETTY_PRINT);
	}	
}
	
if ($type=="tx" && $pattern!="" && $details=="reduce")
{
	if ($subtype=="hash") {
		$valid_access=1;
		header('Content-Type: application/json');
		$query="SELECT * FROM transactions WHERE txid = '$pattern'";
	} else {
		echo json_encode(array('error' => 'Unknown API call'), JSON_PRETTY_PRINT); exit;
	}

	$result = mysqli_query($dbconn,$query);
	$tx_array=array();
	while($row = $result->fetch_assoc())
	{
		$txid=$row['id'];
		$txhash=$row['txid'];
		$time=$row['time'];
		$numvin=$row['numvin'];
		$numvout=$row['numvout'];
		$valuein=$row['valuein'];
		$valueout=$row['valueout'];
		$fee=$row['fee'];
		$coindaysdestroyed=$row['coindaysdestroyed'];
		$avgcoindaysdestroyed=$row['avgcoindaysdestroyed'];		
		if (isset($txid)) {	
			$query="SELECT address, value, coindaysdestroyed, avgcoindaysdestroyed FROM vin WHERE parenttxid = '$txid'";
			$vinresult = mysqli_query($dbconn,$query);
			$vin_array=array();
			while($vinrow = $vinresult->fetch_assoc())
			{
				$address=$vinrow['address'];
				$value=$vinrow['value'];
				$coindaysdestroyed=$vinrow['coindaysdestroyed'];
				$avgcoindaysdestroyed=$vinrow['avgcoindaysdestroyed'];
				$vin=array(
					'address' => $address,
					'value' => $value,
					'coindaysdestroyed' => $coindaysdestroyed,
					'avgcoindaysdestroyed' => $avgcoindaysdestroyed
				);
				array_push($vin_array, $vin);
			}
			
			$query="SELECT value, address FROM vout WHERE parenttxid = '$txid'";
			$voutresult = mysqli_query($dbconn,$query);
			$vout_array=array();
			while($voutrow = $voutresult->fetch_assoc())
			{
				$value=$voutrow['value'];
				$address=$voutrow['address'];
				$vout=array(
					'value' => $value,
					'address' => $address
				);
				array_push($vout_array, $vout);
			}
			
			$tx_array=array(
				'tx_hash' => $txhash,
				'time' => $time,
				'numvin' => $numvin,
				'numvout' => $numvout,
				'valuein' => $valuein,
				'valueout' => $valueout,
				'fee' => $fee,
				'coindaysdestroyed' => $coindaysdestroyed,
				'avgcoindaysdestroyed' => $avgcoindaysdestroyed,
				'vin' => $vin_array,
				'vout' => $vout_array
			);
		}	
	}

		
	if (isset($txid)) {		
		echo json_encode($tx_array, JSON_PRETTY_PRINT);
	} else {
		echo json_encode(array('error' => 'Could not decode hash'), JSON_PRETTY_PRINT);
	}	
}

if ($type=="tx" && $pattern!="" && $details=="less")
{
	if ($subtype=="hash") {
		$valid_access=1;
		header('Content-Type: application/json');
		$query="SELECT * FROM transactions WHERE txid = '$pattern'";
	} else {
		echo json_encode(array('error' => 'Unknown API call'), JSON_PRETTY_PRINT); exit;
	}

	$result = mysqli_query($dbconn,$query);
	$tx_array=array();
	while($row = $result->fetch_assoc())
	{
		$txid=$row['id'];
		$txhash=$row['txid'];
		$time=$row['time'];
		$numvin=$row['numvin'];
		$numvout=$row['numvout'];
		$valuein=$row['valuein'];
		$valueout=$row['valueout'];
		$fee=$row['fee'];
		$coindaysdestroyed=$row['coindaysdestroyed'];
		$avgcoindaysdestroyed=$row['avgcoindaysdestroyed'];		
	}	
	if (isset($txid)) {		
	
		$tx_array=array(
			'tx_hash' => $txhash,
			'time' => $time,
			'numvin' => $numvin,
			'numvout' => $numvout,
			'valuein' => $valuein,
			'valueout' => $valueout,
			'fee' => $fee,
			'coindaysdestroyed' => $coindaysdestroyed,
			'avgcoindaysdestroyed' => $avgcoindaysdestroyed
		);
		echo json_encode($tx_array, JSON_PRETTY_PRINT);
	} else {
		echo json_encode(array('error' => 'Could not decode hash'), JSON_PRETTY_PRINT);
	}	
}


if ($type=="address" && $subtype=="balance" && $pattern!="" && $details=="full")
{
	if ($subtype=="balance") {
		$valid_access=1;
		header('Content-Type: application/json');
		$query="SELECT * FROM address WHERE address = '$pattern'";
	} else {
		echo json_encode(array('error' => 'Unknown API call'), JSON_PRETTY_PRINT); exit;
	}

	$result = mysqli_query($dbconn,$query);
	$tx_array=array();
	while($row = $result->fetch_assoc())
	{
		$address=$row['address'];
		
		$txquery="SELECT tx.id, tx.txid, tx.time, vin.value AS sent, '' AS received
				FROM transactions AS tx
				INNER JOIN vin ON vin.parenttxid = tx.id
				WHERE vin.address = '$address'
				UNION ALL
				SELECT tx.id, tx.txid, tx.time, '' AS sent, vout.value AS received
				FROM transactions AS tx
				INNER JOIN vout ON vout.parenttxid = tx.id
				WHERE vout.address = '$address'
				ORDER BY id DESC";
		$txresult = mysqli_query($dbconn,$txquery);
		$tx_array=array();
		$txidold="";
		$send="";
		$received="";
		while($txrow = $txresult->fetch_assoc()) {	
			$txid=$txrow['id'];
			if ($txidold=="") {$txidold==$txid;}
			if ($txidold!=$txid) {
				$tx=array(
					'tx_hash' => $txhash,
					'time' => $time,
					'sent' => $sent,
					'received' => $received
				);
				array_push($tx_array, $tx);
				$send="";
				$received="";
				$txidold=$txid;
			}
			$txhash=$txrow['txid'];
			$time=$txrow['time'];
			if ($send=="") {
				$sent=$txrow['sent'];
			}
			if ($received=="") {
				$received=$txrow['received'];
			}
			
		}
		$balance=$row['balance'];
		$last_sent=$row['last_sent'];
		$last_received=$row['last_received'];
		$count_sent=$row['count_sent'];
		$count_received=$row['count_received'];
		$total_sent=$row['total_sent'];
		$total_received=$row['total_received'];		
		if (isset($address)) {			
			$address_array=array(
				'address' => $address,
				'balance' => $balance,
				'last_sent' => $last_sent,
				'last_received' => $last_received,
				'count_sent' => $count_sent,
				'count_received' => $count_received,
				'total_sent' => $total_sent,
				'total_received' => $total_received,
				'transactions' => $tx_array
			);
		}	
	}

		
	if (isset($address)) {		
		echo json_encode($address_array, JSON_PRETTY_PRINT);
	} else {
		echo json_encode(array('error' => 'Could not decode hash'), JSON_PRETTY_PRINT);
	}	
}


if ($type=="address" && $subtype=="balance" && $pattern!="" && $details=="reduce")
{
	if ($subtype=="balance") {
		$valid_access=1;
		header('Content-Type: application/json');
		$query="SELECT * FROM address WHERE address = '$pattern'";
	} else {
		echo json_encode(array('error' => 'Unknown API call'), JSON_PRETTY_PRINT); exit;
	}

	$result = mysqli_query($dbconn,$query);
	$tx_array=array();
	while($row = $result->fetch_assoc())
	{
		$address=$row['address'];
		$balance=$row['balance'];
		$last_sent=$row['last_sent'];
		$last_received=$row['last_received'];
		$count_sent=$row['count_sent'];
		$count_received=$row['count_received'];
		$total_sent=$row['total_sent'];
		$total_received=$row['total_received'];		
		if (isset($address)) {			
			$address_array=array(
				'address' => $address,
				'balance' => $balance,
				'last_sent' => $last_sent,
				'last_received' => $last_received,
				'count_sent' => $count_sent,
				'count_received' => $count_received,
				'total_sent' => $total_sent,
				'total_received' => $total_received
			);
		}	
	}

		
	if (isset($address)) {		
		echo json_encode($address_array, JSON_PRETTY_PRINT);
	} else {
		echo json_encode(array('error' => 'Could not decode hash'), JSON_PRETTY_PRINT);
	}	
}

if ($type=="address" && $subtype=="balance" && $pattern!="" && $details=="less")
{
	if ($subtype=="balance") {
		$valid_access=1;
		header('Content-Type: application/json');
		$query="SELECT address,balance FROM address WHERE address = '$pattern'";
	} else {
		echo json_encode(array('error' => 'Unknown API call'), JSON_PRETTY_PRINT); exit;
	}

	$result = mysqli_query($dbconn,$query);
	$tx_array=array();
	while($row = $result->fetch_assoc())
	{
		$address=$row['address'];
		$balance=$row['balance'];
		if (isset($address)) {			
			$address_array=array(
				'address' => $address,
				'balance' => $balance
			);
		}	
	}

		
	if (isset($address)) {		
		echo json_encode($address_array, JSON_PRETTY_PRINT);
	} else {
		echo json_encode(array('error' => 'Could not decode hash'), JSON_PRETTY_PRINT);
	}	
}

if ($type=="address" && $subtype=="isvalid" && $pattern!="" && $details=="reduce")
{
	if ($subtype=="isvalid") {
		$valid_access=1;
		$array=$emercoin->validateaddress($pattern);
		echo $array["isvalid"];
	}
	
}

if ($type=="address" && $subtype=="ismine" && $pattern!="" && $details=="reduce")
{
	if ($subtype=="ismine") {
		$valid_access=1;
		$array=$emercoin->validateaddress($pattern);
		echo $array["ismine"];
	}
	
}

if ($type=="stats" && $subtype!="")
{
	if ($subtype=="block_height") {
		$valid_access=1;
		header('Content-Type: application/json');
		$query="SELECT height FROM blocks ORDER BY height DESC LIMIT 1";
		$result = mysqli_query($dbconn,$query);
		$height_array=array();
		while($row = $result->fetch_assoc())
		{
			$height=$row['height'];
			if (isset($height)) {			
				$height_array=array(
					'block_height' => $height
				);
			}	
		}
		if (isset($height)) {		
			echo json_encode($height_array, JSON_PRETTY_PRINT);
		} else {
			echo json_encode(array('error' => 'Could not decode hash'), JSON_PRETTY_PRINT);
		}
	} else if ($subtype=="coin_supply") {
		$valid_access=1;
		header('Content-Type: application/json');
		$query="SELECT total_coins FROM blocks ORDER BY height DESC LIMIT 1";
		$result = mysqli_query($dbconn,$query);
		$supply_array=array();
		while($row = $result->fetch_assoc())
		{
			$total_coins=$row['total_coins'];
			if (isset($total_coins)) {			
				$supply_array=array(
					'coin_supply' => round($total_coins,6)
				);
			}	
		}
		if (isset($total_coins)) {		
			echo json_encode($supply_array, JSON_PRETTY_PRINT);
		} else {
			echo json_encode(array('error' => 'Could not decode hash'), JSON_PRETTY_PRINT);
		}

	} else {
		echo json_encode(array('error' => 'Unknown API call'), JSON_PRETTY_PRINT); exit;
	}

		
}

if ($valid_access==0) {
	echo json_encode(array('error' => 'Unknown API call'), JSON_PRETTY_PRINT);
}

?>
