<?php
if (isset($_SERVER['REQUEST_URI'])) {
	$URI=explode('/',$_SERVER['REQUEST_URI']);
	if ($URI[1]=="block") {
		if (isset($URI[2])) {
			$hash=$URI[2];
		}
	}
}
function TrimTrailingZeroes($nbr) {
    return strpos($nbr,'.')!==false ? rtrim(rtrim($nbr,'0'),'.') : $nbr;
}

echo '<div class="container">';
if (isset($hash) && $hash!="") {
	if (is_numeric($hash)) {
		$query = "SELECT hash FROM blocks WHERE height = '$hash'";
		$result = $dbconn->query($query);
		while($row = $result->fetch_assoc())
		{
			$hash=$row['hash'];
		}
	}
	$query = "SELECT id, height, size, previousblockhash, time, flags, difficulty, total_coins, total_avgcoindays, nonce, merkleroot, numtx, numvin, numvout, valuein, valueout, mint, fee, coindaysdestroyed, avgcoindaysdestroyed
		FROM blocks
		WHERE hash = '$hash'";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$block_id=$row['id'];
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
	} else {
		echo '<h3>'.lang("UNKNOWN_BLOCK").'</h3>';
	}

	if (isset($block_id)) {
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

		echo '
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">'.lang("NVS_NVS").'</h3>
			</div>
			<div class="panel-body">';

		$query = "SELECT * FROM nvs WHERE registered_at = '$height'";
		$result = $dbconn->query($query);
		while($row = $result->fetch_assoc())
		{
			$nvs_name=$row['name'];
			$nvs_value=$row['value'];
			$nvs_type=$row['type'];
			$nvs_isbase64=$row['isbase64'];
			if ($nvs_isbase64==1) {
				$nvs_value=utf8_decode(base64_decode($nvs_value));
			}
			$nvs_expires_at=$row['expires_at'];
			echo '<b>'.lang("NAME_NAME").':</b> '.$nvs_name.'<br>';
			try {
				error_reporting(0);
				$history=$emercoin->name_history($nvs_name);
				echo '<b>Value History:</b> <br>';
				$days_added=0;
				$initialtime=$history[0]['time'];
				foreach ($history as $element) {
					echo $element['value'].'<br>';
					$owner=$element['address'];
					$days_added+=$element['days_added'];
				}
				echo "<br><b>Owner:</b> ".$owner;
				$valid_until=date('d.m.Y', bcadd($initialtime,bcmul($days_added,86400,0),0));
				echo "<br><b>Valid until:</b> ".$valid_until.'<br>';
			} catch (Exception $e) {
				echo '<p><b>Name history is not available.</b><br/><p>';
				echo lang("VALUE_VALUE").': '.$nvs_value.'<br>';
			}

			if ($nvs_type=="dpo") {
				echo '<br><b>DPO</b><br>';
				$nameArr=explode(':',$nvs_name);
				$brand=$nameArr[1];
				$sn=$nameArr[2];

				if ($brand!='') {
					$brand_param = htmlspecialchars($brand);
					try {
						error_reporting(0);
						$brand_info=$emercoin->name_show('dpo:'.$brand);
						echo "<p><b>Brand info: $brand_param</b><br/>";
						$brandtok=Tokenize($brand_info);
						PrintTok($brandtok);
					} catch (Exception $e) {
						echo '<p><b>Brand "'.$brand_param.'" not found</b><br/><p>';
					}
				}
				if ($sn!='') {
					$sn = preg_replace('/[^0-9A-Za-z_-]/', '', $sn);
					echo "<p><b>Serial: $sn</b></p>";
					$filt_key = 'dpo:'.$brand.':'.$sn.':';
					$filt_list = $emercoin->name_filter($filt_key);
					if(empty($filt_list)) {
					echo "Serial $sn not found in the Emercoin blockchain<br>";
					echo "<b>Verification: <font color='red'>FAILED</font></b>";
					} else
					foreach($filt_list as $item) { // Yterate item list
					  $item =  $emercoin->name_show($item['name']); // fetch full item record
					  $tokens = Tokenize($item);
					  PrintTok($tokens);
					  $tokensCopy=array_change_key_case($tokens, CASE_LOWER);
						if (isset($tokensCopy['signature'])) {
						  try {
							$ver = $emercoin->verifymessage($brand_info['address'], $tokensCopy['signature'], $tokens['__FOR_SIG__'])?
							  "<font color='green'>PASSED</font><br><small class='text-muted'>(signature verified)</small>" : "<font color='red'>FAILED</font>";
							echo "<b>Verification: $ver</b>";
						  } catch(Exception $ex) {
							echo "<br></br><b>Verification: <font color='red'>FAILED</font></b><br>";
							//echo "Blockchain request error: ". $ex->getMessage() . "\n";
							echo "<small class='text-muted'>Blockchain request error - CALL: verifymessage '" . $brand_info['address']. "' '" . $tokensCopy['signature'] . "' '" . $tokens['__FOR_SIG__'] . "'</small><p>";
						   }
						  echo "</p>";
						} else {
							$history=$emercoin->name_history($item['name']);
							if ($brand_info['address']==$history[0]['address']) {
								 echo "<b>Verification: <font color='green'>PASSED</font></b><br><small class='text-muted'>(address verified)</small>";
							} else {
								echo "<b>Verification: <font red='green'>FAILED</font></b><br><small class='text-muted'></small>";
							}
						}
					} // foreach $filt_list
				}
			}
			echo '<hr>';
		}
		echo '</div>';

	}
} else {
	echo '<h3>No Block Provided</h3>';
}
echo '</div>';














function isImage($url)
  {
     $params = array('http' => array(
                  'method' => 'HEAD'
               ));
     $ctx = stream_context_create($params);
     $fp = @fopen($url, 'rb', false, $ctx);
     if (!$fp)
        return false;  // Problem with url

    $meta = stream_get_meta_data($fp);
    if ($meta === false)
    {
        fclose($fp);
        return false;  // Problem reading data from url
    }

    $wrapper_data = $meta["wrapper_data"];
    if(is_array($wrapper_data)){
      foreach(array_keys($wrapper_data) as $hh){
          if (substr($wrapper_data[$hh], 0, 19) == "Content-Type: image") // strlen("Content-Type: image") == 19
          {
            fclose($fp);
            return true;
          }
      }
    }

    fclose($fp);
    return false;
  }
function makeClickableLinks($s) {
	//$remove = array("\n", "\r\n", "\r");
	//$s = str_replace($remove, ' <br> ', $s);
	$org=$s;
	$text=explode (' ',$s);
	foreach ($text as $s) {
		if (strpos($s,'http') !== false || strpos($s,'ftp') !== false) {
			if (isImage($s)) {
				$s=preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.-]*(\?\S+)?)?)?)@', '<a href="$1"><img style="height:auto; width:auto; max-width:75px; max-height:75px;" src="$1"></a>', $s);
			} else {
				$s=preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.-]*(\?\S+)?)?)?)@', '<a href="$1">$1</a>', $s);
			}
			return $s;
		} else if (strpos($s,'@') !== false && strpos($s,'.') !== false) {
				$pattern = '#([0-9a-z]([-_.]?[0-9a-z])*@[0-9a-z]([-.]?[0-9a-z])*\\.';
				$pattern .= '[a-wyz][a-z](fo|g|l|m|mes|o|op|pa|ro|seum|t|u|v|z)?)#i';
				$replacement = '<a href="mailto:\\1">\\1</a>';
				$s = preg_replace($pattern, $replacement, $s);
				return $s;
			}
			return $org;
		}
}

function Tokenize($item) {
			$tokens  = array();
			$for_sig = array($item['name']);
			foreach(explode(PHP_EOL, $item['value']) as $val_line) {
			  if(substr($val_line, 0, 2) === "F-")
				array_push($for_sig, trim($val_line));
				$tok=explode("=", $val_line);
				if (strtolower($tok[0])=="signature") { $tok[1].="="; }
				$tokens[$tok[0]] = utf8_decode(trim($tok[1]));
			}
			$tokens['__FOR_SIG__'] =  join('|', $for_sig);
			return $tokens;
		}

function PrintTok($tokens) {
  echo "<pre>";
  foreach($tokens as $k => $v)
    if(substr($k, 0, 2) !== "__")
      echo "\t" . htmlspecialchars($k) . ": " .  makeClickableLinks(htmlspecialchars($v)) . "\n";
  echo "</pre>";
}
?>
