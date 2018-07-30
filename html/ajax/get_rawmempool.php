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
	$mempool=$emercoin->getrawmempool();

	echo '<div class="panel-heading"><b>'.lang('UNCONFIRMED_TRANSACTIONS').'</b></div>
	<table class="table">
	<thead>
	<tr><th>'.lang('TX_ID').'</th><th>'.lang('VALUE_EMC').'</th></tr>
	</thead>
	<tbody>';
	foreach ($mempool as $rawtx) {

			$txhash=$emercoin->getrawtransaction($rawtx,1);
			$tx_id_short = substr($txhash['txid'], 0, 4)."...".substr($txhash['txid'], -4);

		try {
			$values=array();
			$values["valuein"]=0;
			if (isset($txhash["time"])) {
				$values["time"]=$txhash["time"];
			} else {
				$values["time"]="";
			}
			$coindaysdestroyed=0;
			$values["coindaysdestroyed"]=0;
			$avgcoindaysdestroyed=0;
			$values["avgcoindaysdestroyed"]=0;
			$receivetime=0;
			$values["countvin"]=0;
			$inputs="";
			foreach ($txhash["vin"] as $vin) {
				//echo $blockid." ";
				//echo $txdbid." ";
				$coinbase="";
				$vout="";
				$asm="";
				$hex="";
				$sequence="";
				$address="";
				$value="";
				$vintxid="";
				if (isset($vin["txid"])) {
					//echo $vin["txid"]." ";
					$vintxid=$vin["txid"];
				}
				if (isset($vin["coinbase"])) {
					//echo $vin["coinbase"]." ";
					$coinbase=$vin["coinbase"];
				}
				if (isset($vin["coinbase"])) {
					//echo $vin["coinbase"]." ";
					$coinbase=$vin["coinbase"];
				}
				if (isset($vin["vout"])) {
					//echo $vin["vout"]." ";
					$vout=$vin["vout"];
					$tx=$emercoin->getrawtransaction($vintxid,1);
					$value=$tx["vout"][$vout]["value"];
					if (isset($tx["vout"][$vout]["scriptPubKey"]["addresses"][0])) {
						$address=$tx["vout"][$vout]["scriptPubKey"]["addresses"][0];
						if (!isset($sentaddress[$address]["sent"])) {
							$sentaddress[$address]["sent"]=$value;
							$sentaddress[$address]["countsent"]=1;
						} else {
							$sentaddress[$address]["sent"]=bcadd($sentaddress[$address]["sent"],$value,8);
							$sentaddress[$address]["countsent"]++;
						}
						$sentaddress[$address]["time"]=$values["time"];
					}
					if (isset($tx["time"])) {
						$receivetime=$tx["time"];
						$timediff=bcsub($values["time"],$receivetime,9);
						$coindaysdestroyed=bcmul($value,bcdiv($timediff,86400,9),9);
						//echo bcdiv($timediff,86400,9)." ";
						$values["coindaysdestroyed"]=bcadd($values["coindaysdestroyed"],$coindaysdestroyed,9);
						if ($timediff!=0) {
							$avgcoindaysdestroyed=bcdiv($coindaysdestroyed,$value,9);
						} else {
							$avgcoindaysdestroyed=0;
						}
						$values["avgcoindaysdestroyed"]=$avgcoindaysdestroyed;
						//echo $coindaysdestroyed." = ".$value." * "."((".$values["time"]."-".$receivetime.")/86400 \n";
					}

					//echo $value." ".$address."\n";
				}
				if (isset($vin["scriptSig"])) {
					//echo $vin["scriptSig"]["asm"];
					$asm=$vin["scriptSig"]["asm"];
					//echo $vin["scriptSig"]["hex"];
					$hex=$vin["scriptSig"]["hex"];
				}
				if (isset($vin["sequence"])) {
					//echo $vin["sequence"]." ";
					$sequence=$vin["sequence"];
				}
				$values["valuein"]=$values["valuein"]+$value;
				$values["countvin"]++;
			}
			$values["sentaddressarray"]=$sentaddress;

			//echo $values["valuein"];
		} catch (Exception $e) {}


		try {
			$values["valueout"]=0;
			$values["countvout"]=0;
			$inputs="";
			foreach ($txhash["vout"] as $vout) {
				//echo $blockid." ";
				//echo $txdbid." ";
				$n="";
				$asm="";
				$hex="";
				$reqsigs="";
				$type="";
				$address="";
				$value="";
				if (isset($vout["value"])) {
					//echo $vout["value"]." ";
					$value=$vout["value"];
				}
				if (isset($vout["n"])) {
					//echo $vout["n"]." ";
					$n=$vout["n"];
				}
				if (isset($vout["scriptPubKey"]["asm"])) {
					//echo $vout["scriptPubKey"]["asm"]." ";
					$asm=$vout["scriptPubKey"]["asm"];
				}
				if (isset($vout["scriptPubKey"]["hex"])) {
					//echo $vout["scriptPubKey"]["hex"]." ";
					$hex=$vout["scriptPubKey"]["hex"];
				}
				if (isset($vout["scriptSig"]["reqSigs"])) {
					//echo $vout["scriptPubKey"]["reqSigs"]." ";
					$reqsigs=$vout["scriptPubKey"]["reqSigs"];
				}
				if (isset($vout["scriptSig"]["type"])) {
					//echo $vout["scriptPubKey"]["type"]." ";
					$type=$vout["scriptPubKey"]["type"];
				}
				if (isset($vout["scriptPubKey"]["addresses"][0])) {
					//echo $vout["scriptPubKey"]["addresses"][0]." ";
					$address=$vout["scriptPubKey"]["addresses"][0];
					if (!isset($receiveaddress[$address]["received"])) {
							$receiveaddress[$address]["received"]=$value;
						} else {
							$receiveaddress[$address]["received"]=bcadd($receiveaddress[$address]["received"],$value,8);
						}
				}
				$values["valueout"]=$values["valueout"]+$value;
				$values["countvout"]++;
			}
			$values["receiveaddressarray"]=$receiveaddress;


				echo '<tr><td>'.$tx_id_short.'</td><td>'.$values["valueout"].'</td></tr>';

		} catch (Exception $e) {}
	}
	echo "</tbody></table>";
	if (!isset($txhash)) {
		echo lang('THERE_TRANSACTIONS');
	}

	?>
