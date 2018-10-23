<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once __DIR__ . '/include.php';
function getblockinfo($dbconn, $emercoin, $hash) {
	$block=$emercoin->getblock($hash);
	$new_ID="";
	$update_ID="";
	$current_id="";
	$hash=$block['hash'];
	$size=$block['size'];
	$height=$block['height'];
	$version=$block['version'];
	$merkleroot=$block['merkleroot'];
	$time=$block['time'];
	$blocktime = $time;
	//$time=strtotime($time);
	$nonce=$block['nonce'];
	$bits=$block['bits'];
	$difficulty=$block['difficulty'];
	$mint=$block['mint'];
	$total_coins=0;
	$oldmint=0;
	$oldtime=0;
	if (isset($block['previousblockhash'])){
		$previousblockhash=$block['previousblockhash'];
		$query="SELECT time, mint, total_coins, total_coindays FROM blocks WHERE hash = '$previousblockhash'";
		$result = $dbconn->query($query);
		while($row = $result->fetch_assoc())
		{
			$oldtime=$row['time'];
			$total_coins=$row['total_coins'];
			$total_coins_old=$row['total_coins'];
			$total_coindays=$row['total_coindays'];
		}
	} else {
		$previousblockhash="";
	}
	$flags=$block['flags'];
	$proofhash=$block['proofhash'];
	$entropybit=$block['entropybit'];
	$modifier=$block['modifier'];
	$modifierchecksum=$block['modifierchecksum'];

	$hash_in_db="";
	$query = "SELECT id, hash, status FROM blocks WHERE id = ".($height+1);
	$result = $dbconn->query($query);
	$dbconn->error;
	$current_status="";
		while($row = $result->fetch_assoc())
		{
			$hash_in_db=$row['hash'];
			$current_id=$row['id'];
			$current_status=$row['status'];
		}
	if ($hash_in_db == "" && $current_status!="0") {
		$current_status="0";
		$query="INSERT INTO blocks
		(hash, size, height, version, merkleroot, time, nonce, bits, difficulty, mint, previousblockhash, flags, proofhash, entropybit, modifier, modifierchecksum, status)
		VALUES
		('$hash', $size, $height, $version, '$merkleroot', $time, $nonce, '$bits', '$difficulty', '$mint', '$previousblockhash', '$flags', '$proofhash', $entropybit, '$modifier', '$modifierchecksum', $current_status);";
		$result = $dbconn->query($query);
		$new_ID=$dbconn -> insert_id;
		$dbconn->error;
	}
	else if ($hash_in_db != $hash || $current_status=="0") {
		$current_status="0";
		$query="UPDATE blocks
		SET hash = '$hash',
		size = $size,
		height = $height,
		version = $version,
		merkleroot = '$merkleroot',
		time = $time,
		nonce = $nonce,
		bits = '$bits',
		difficulty = '$difficulty',
		mint = '$mint',
		previousblockhash = '$previousblockhash',
		flags = '$flags',
		proofhash = '$proofhash',
		entropybit = $entropybit,
		modifier = '$modifier',
		modifierchecksum = '$modifierchecksum',
		status = $current_status
		WHERE hash = '$hash_in_db'";
		$result = $dbconn->query($query);
		$update_ID="updated";
	}

	if ($new_ID!="") {
		$valueintotal=0;
		$valueouttotal=0;
		$feetotal=0;
		$coindaysdestroyedtotal=0;
		$avgcoindaysdestroyedtotal=0;
		$counttx=0;
		$countvin=0;
		$countvintotal=0;
		$countvout=0;
		$countvouttotal=0;
		$sentaddress=array();
		$receiveaddress=array();
		$addresses=array();
		$senttransactionaddress=array();
		$addressquery="";
		foreach ($block['tx'] as $txid) {
			$query="INSERT INTO transactions
			(blockid, txid)
			VALUES
			('$new_ID', '$txid' )";
			$result = $dbconn->query($query);
			$tx_ID=$dbconn -> insert_id;
			$vin=gettxinput($dbconn, $emercoin, $txid, $tx_ID, $new_ID, $sentaddress);
			foreach ($vin['sentaddressarray'] as $address => $value) {
				if ($addressquery=="") {
					$addressquery="address='".$address."'";
				} else {
					$addressquery.="OR address='".$address."'";
				}
				if (!isset($addresses[$address]['value'])) {
					$addresses[$address]['value']=bcsub(0,$value['sent'],8);
					$addresses[$address]['senttime']=$value['time'];
				} else {
					$addresses[$address]['value']=bcsub($addresses[$address]['value'],$value['sent'],8);
					$addresses[$address]['senttime']=$value['time'];
				}
				if (!isset($addresses[$address]['sentvalue'])) {
					$addresses[$address]['sentvalue']=$value['sent'];
					$addresses[$address]['sentcount']=1;
				} else {
					$addresses[$address]['sentvalue']=bcadd($addresses[$address]['sentvalue'],$value['sent'],8);
					$addresses[$address]['sentcount']++;
				}
				$senttransactionaddress[$txid][$address]="1";
			}
			$valuein=$vin["valuein"];
			$countvin=$vin["countvin"];
			$countvintotal=$countvintotal+$countvin;
			$time=$vin["time"];
			$coindaysdestroyed=$vin["coindaysdestroyed"];
			$avgcoindaysdestroyed=$vin["avgcoindaysdestroyed"];
			$vout=gettxoutput($dbconn, $emercoin, $txid, $tx_ID, $new_ID, $time, $receiveaddress);
			foreach ($vout['receiveaddressarray'] as $address => $value) {
				if ($addressquery=="") {
					$addressquery="address='".$address."'";
				} else {
					$addressquery.="OR address='".$address."'";
				}
				if (!isset($addresses[$address]['value'])) {
					$addresses[$address]['value']=$value['received'];
					$addresses[$address]['receivetime']=$value['time'];
				} else {
					$addresses[$address]['value']=bcadd($addresses[$address]['value'],$value['received'],8);
					$addresses[$address]['receivetime']=$value['time'];
				}
				if (!isset($addresses[$address]['receivedvalue'])) {
					$addresses[$address]['receivedvalue']=$value['received'];
					$addresses[$address]['receivedcount']=1;
				} else {
					$addresses[$address]['receivedvalue']=bcadd($addresses[$address]['receivedvalue'],$value['received'],8);
					$addresses[$address]['receivedcount']++;
				}
			}
			$valueout=$vout["valueout"];
			$countvout=$vout["countvout"];
			$countvouttotal=$countvouttotal+$countvout;
			if ($valuein==0) {
				$fee=0;
			} else {
				$fee=$valuein-$valueout;
			}
			$fee=round($fee,6);
			$query="UPDATE transactions
			SET numvin = $countvin,
			numvout = $countvout,
			valuein = '$valuein',
			valueout = '$valueout',
			time = $time,
			fee = '$fee',
			coindaysdestroyed = '$coindaysdestroyed',
			avgcoindaysdestroyed = '$avgcoindaysdestroyed'
			WHERE id = '$tx_ID'";
			$result = $dbconn->query($query);
			$valueintotal=$valueintotal+$valuein;
			$valueouttotal=$valueouttotal+$valueout;
			$feetotal=round($feetotal+$fee,6);
			$coindaysdestroyedtotal=bcadd($coindaysdestroyedtotal,$coindaysdestroyed,9);
			$counttx++;
		}
		if ($valueintotal!=0) {
			$avgcoindaysdestroyedtotal=bcdiv($coindaysdestroyedtotal,$valueintotal,9);
		} else {
			$avgcoindaysdestroyedtotal=0;
		}
		if (strpos($flags,'proof-of-work') !== false) {
			$total_coins=bcsub(bcadd($total_coins,$mint,9),$feetotal,9);
		} else if (strpos($flags,'proof-of-stake') !== false) {
			$total_coins=bcsub(bcadd($total_coins,$mint,9),bcadd($mint,$feetotal,9),9);
		}
		//calculate total_coindays bcdiv($timediff,86400,9)
		$total_coindays=bcadd($total_coindays,bcsub(bcmul($total_coins_old,bcdiv(bcsub($blocktime,$oldtime,9),86400,9),9),$coindaysdestroyedtotal,9),9);
		if ($total_coins!=0) {
			$total_avgcoindays=bcdiv($total_coindays,$total_coins,9);
		} else {
			$total_avgcoindays=0;
		}
	}

	if ($update_ID!="") {
		$query="DELETE FROM transactions WHERE blockid = '$current_id'";
		$result = $dbconn->query($query);
		$dbconn->error;
		$query="DELETE FROM vin WHERE blockid = '$current_id'";
		$result = $dbconn->query($query);
		$dbconn->error;
		$query="DELETE FROM vout WHERE blockid = '$current_id'";
		$result = $dbconn->query($query);
		$dbconn->error;
		$valueintotal=0;
		$valueouttotal=0;
		$feetotal=0;
		$coindaysdestroyedtotal=0;
		$avgcoindaysdestroyedtotal=0;
		$counttx=0;
		$countvin=0;
		$countvintotal=0;
		$countvout=0;
		$countvouttotal=0;
		$senttransactionaddress=array();
		$sentaddress=array();
		$receiveaddress=array();
		$addressquery="";
		foreach ($block['tx'] as $txid) {
			$query="INSERT INTO transactions
			(blockid, txid)
			VALUES
			('$current_id', '$txid' )";
			$result = $dbconn->query($query);
			$tx_ID=$dbconn -> insert_id;
			$vin=gettxinput($dbconn, $emercoin, $txid, $tx_ID, $new_ID, $sentaddress);
			foreach ($vin['sentaddressarray'] as $address => $value) {
				if ($addressquery=="") {
					$addressquery="address='".$address."'";
				} else {
					$addressquery.="OR address='".$address."'";
				}
				if (!isset($addresses[$address]['value'])) {
					$addresses[$address]['value']=bcsub(0,$value['sent'],8);
					$addresses[$address]['senttime']=$value['time'];
				} else {
					$addresses[$address]['value']=bcsub($addresses[$address]['value'],$value['sent'],8);
					$addresses[$address]['senttime']=$value['time'];
				}
				if (!isset($addresses[$address]['sentvalue'])) {
					$addresses[$address]['sentvalue']=$value['sent'];
					$addresses[$address]['sentcount']=1;
				} else {
					$addresses[$address]['sentvalue']=bcadd($addresses[$address]['sentvalue'],$value['sent'],8);
					$addresses[$address]['sentcount']++;
				}
				$senttransactionaddress[$txid][$address]="1";
			}
			$valuein=$vin["valuein"];
			$countvin=$vin["countvin"];
			$countvintotal=$countvintotal+$countvin;
			$time=$vin["time"];
			$coindaysdestroyed=$vin["coindaysdestroyed"];
			$avgcoindaysdestroyed=$vin["avgcoindaysdestroyed"];
			$vout=gettxoutput($dbconn, $emercoin, $txid, $tx_ID, $new_ID, $time, $receiveaddress);
			foreach ($vout['receiveaddressarray'] as $address => $value) {
				if ($addressquery=="") {
					$addressquery="address='".$address."'";
				} else {
					$addressquery.="OR address='".$address."'";
				}
				if (!isset($addresses[$address]['value'])) {
					$addresses[$address]['value']=$value['received'];
					$addresses[$address]['receivetime']=$value['time'];
				} else {
					$addresses[$address]['value']=bcadd($addresses[$address]['value'],$value['received'],8);
					$addresses[$address]['receivetime']=$value['time'];
				}
				if (!isset($addresses[$address]['receivedvalue'])) {
					$addresses[$address]['receivedvalue']=$value['received'];
					$addresses[$address]['receivedcount']=1;
				} else {
					$addresses[$address]['receivedvalue']=bcadd($addresses[$address]['receivedvalue'],$value['received'],8);
					$addresses[$address]['receivedcount']++;
				}
			}
			$valueout=$vout["valueout"];
			$countvout=$vout["countvout"];
			$countvouttotal=$countvouttotal+$countvout;
			if ($valuein==0) {
				$fee=0;
			} else {
				$fee=$valuein-$valueout;
			}
			$fee=round($fee,6);
			$query="UPDATE transactions
			SET numvin = $countvin,
			numvout = $countvout,
			valuein = '$valuein',
			valueout = '$valueout',
			time = $time,
			fee = '$fee',
			coindaysdestroyed = '$coindaysdestroyed',
			avgcoindaysdestroyed = '$avgcoindaysdestroyed'
			WHERE id = '$tx_ID'";
			$result = $dbconn->query($query);
			$valueintotal=$valueintotal+$valuein;
			$valueouttotal=$valueouttotal+$valueout;
			$feetotal=round($feetotal+$fee,6);
			$coindaysdestroyedtotal=bcadd($coindaysdestroyedtotal,$coindaysdestroyed,9);
			$counttx++;
		}
		if ($valueintotal!=0) {
			$avgcoindaysdestroyedtotal=bcdiv($coindaysdestroyedtotal,$valueintotal,9);
		} else {
			$avgcoindaysdestroyedtotal=0;
		}
		if (strpos($flags,'proof-of-work') !== false) {
			$total_coins=bcsub(bcadd($total_coins,$mint,9),$feetotal,9);
		} else if (strpos($flags,'proof-of-stake') !== false) {
			$total_coins=bcsub(bcadd($total_coins,$mint,9),bcadd($mint,$feetotal,9),9);
		}
		//calculate total_coindays
		$total_coindays=bcadd($total_coindays,bcsub(bcmul($total_coins_old,bcdiv(bcsub($blocktime,$oldtime,9),86400,9),9),$coindaysdestroyedtotal,9),9);
		if ($total_coins!=0) {
			$total_avgcoindays=bcdiv($total_coindays,$total_coins,9);
		} else {
			$total_avgcoindays=0;
		}

	}

	$getinfo=$emercoin->getinfo();
	if ($update_ID!="" || $new_ID!="") {
		$query="SELECT address FROM address WHERE status != '1'";
		$result = $dbconn->query($query);
		while($row = $result->fetch_assoc())
		{
			$address=$row['address'];
			$query2="SELECT COUNT(vin.value) AS sent_count, SUM(vin.value) AS sent_total, '' AS received_count, '' AS received_total
			FROM vin
			WHERE vin.address = '$address'
			UNION
			SELECT '' AS sent_count, '' AS sent_total, COUNT(vout.value) AS received_count, SUM(vout.value) AS received_total
			FROM vout
			WHERE vout.address = '$address'
			";
			$result2 = $dbconn->query($query2);
			$sent_total=0;
			$sent_count=0;
			$received_total=0;
			$received_count=0;
			while($row2 = $result2->fetch_assoc())
			{
				if ($row2['sent_total']!="") {
					$sent_total=round($row2['sent_total'],8);
					$sent_count=$row2['sent_count'];
				}
				if ($row2['received_total']!="") {
					$received_total=round($row2['received_total'],8);
					$received_count=$row2['received_count'];
				}
			}
			$balance=round(bcsub($received_total,$sent_total,8),8);
			$query3="UPDATE address
			SET balance = '$balance',
			count_sent = $sent_count,
			count_received = $received_count,
			total_sent = '$sent_total',
			total_received = '$received_total',
			status = 1
			WHERE address = '$address'";
			$result3 = $dbconn->query($query3);
		}

		getaddressinfo($dbconn, $addresses, $addressquery, $senttransactionaddress);

		//get used/unused addresses
		$query="SELECT COUNT(address) as total_addresses_used FROM `address` WHERE balance >0";
		$result = $dbconn->query($query);
		while($row = $result->fetch_assoc())
		{
			$total_addresses_used=$row['total_addresses_used'];
		}
		$query="SELECT COUNT(address) as total_addresses_unused FROM `address` WHERE balance =0";
		$result = $dbconn->query($query);
		while($row = $result->fetch_assoc())
		{
			$total_addresses_unused=$row['total_addresses_unused'];
		}

		$current_status="1";
		$block=$emercoin->getblock($hash);
		$time=$block['time'];
		$query="UPDATE blocks
		SET time = $time,
		numtx = $counttx,
		numvin = $countvintotal,
		numvout = $countvouttotal,
		valuein = '$valueintotal',
		valueout = '$valueouttotal',
		fee = '$feetotal',
		total_coins = '$total_coins',
		coindaysdestroyed = '$coindaysdestroyedtotal',
		avgcoindaysdestroyed = '$avgcoindaysdestroyedtotal',
		total_coindays = '$total_coindays',
		total_avgcoindays = '$total_avgcoindays',
		total_addresses_used = $total_addresses_used,
		total_addresses_unused = $total_addresses_unused,
		status = $current_status
		";
		if ($new_ID!="") {
			$query.="WHERE id = '$new_ID'";
		}
		else if ($update_ID!="") {
			$query.="WHERE id = '$current_id'";
		}
		$result = $dbconn->query($query);

		//echo $new_ID."\n";

		$query="UPDATE address
		SET status = '1'
		WHERE status = '0'";
		$result = $dbconn->query($query);


		if (isset($getinfo['blocks'])) {
			$currentblocks=$getinfo['blocks'];
			getnvsinfo($dbconn, $emercoin, $currentblocks);
		}
	}

	if (isset($block['nextblockhash']) && isset($getinfo['blocks']) && isset($block['height'])){
		$nextblockhash=$block['nextblockhash'];
		$currentblocks=$getinfo['blocks'];
		$height=$block['height'];
			getblockinfo($dbconn, $emercoin, $nextblockhash);
	}
}

function getnvsinfo($dbconn, $emercoin, $height) {
	$inputs="";
	$query="TRUNCATE TABLE nvs";

        if (!$result = $dbconn->query($query)) {
                printf("Errormessage_select: %s\n", $dbconn->error);
        }
/*	$valueindb=array();
	$query="SELECT * FROM nvs";

	if (!$result = $dbconn->query($query)) {
		printf("Errormessage_select: %s\n", $dbconn->error);
	}
	while($row = $result->fetch_assoc())
	{
		$valueindb[addslashes($row['name'])]['value']=addslashes($row['value']);
		$valueindb[addslashes($row['name'])]['type']=addslashes($row['type']);
		$valueindb[addslashes($row['name'])]['registered_at']=$row['registered_at'];
		$valueindb[addslashes($row['name'])]['expires_at']=$row['expires_at'];
	}
*/	$nvs=$emercoin->name_filter();
	$count=0;
	foreach ($nvs as $nv) {
		$name=addslashes($nv['name']);
		$type="";
		if (strpos($name, ':') !== false) {
			$nameArr=explode(':',$name);
			$type=addslashes($nameArr[0]);
		}
		$value=addslashes($nv['value']);
		$isbase64=0;
		if (!ctype_print($value)) {
			$isbase64=1;
			$value=base64_encode($value);
		}
		$registered_at=$nv['registered_at'];
		$expires_at=($height+$nv['expires_in']);
//		if (!array_key_exists($name,$valueindb)) {
				$inputs.="('$name', '$value', '$type', '$isbase64', '$registered_at', '$expires_at'),";
/*		} else {
				if ($value!=$valueindb[$name]['value']||$registered_at!=$valueindb[$name]['registered_at']||$expires_at!=$valueindb[$name]['expires_at']) {
				$query="UPDATE nvs
				SET value='$value',
				isbase64='$isbase64',
				registered_at='$registered_at',
				expires_at='$expires_at'
				WHERE name='$name'";
				if (!$dbconn->query($query)) {
					printf("Errormessage_update: %s\n", $dbconn->error);
				}
			}
		}
*/
		$count++;
		if ($count == 10000) {
			$inputs=rtrim($inputs, ",");
			$inputs.=";";
			$query="INSERT INTO nvs
			(name, value, type, isbase64, registered_at, expires_at)
			VALUES".$inputs;
			//echo $query;
			if (!$dbconn->query($query)) {
				printf("Errormessage_insert: %s\n", $dbconn->error);
			}
			$inputs='';
			$count=0;
		}
	}
	if ($inputs!='') {
		$inputs=rtrim($inputs, ",");
		$inputs.=";";
		$query="INSERT INTO nvs
		(name, value, type, isbase64, registered_at, expires_at)
		VALUES".$inputs;
		//echo $query;
		if (!$dbconn->query($query)) {
			printf("Errormessage_insert: %s\n", $dbconn->error);
		}
	}
};

function getaddressinfo($dbconn, $addresses, $addressquery, $senttransactionaddress) {
	if ($addressquery!="") {
		$query="SELECT id, address, account, balance, count_sent, count_received, total_sent, total_received
				FROM address
				WHERE ".$addressquery."
				ORDER BY id
				";
		$result = $dbconn->query($query);
		$addressindb=array();
		while($row = $result->fetch_assoc())
		{
			$addressindb[$row['address']]=$row['account'];
			$addresses[$row['address']]['value']=round(bcadd($addresses[$row['address']]['value'],$row['balance'],8),8);
			$addresses[$row['address']]['account']=$row['account'];
			$addresses[$row['address']]['id']=$row['id'];
			if (isset($addresses[$row['address']]['sentvalue'])) {
				$addresses[$row['address']]['sentvalue']=round(bcadd($addresses[$row['address']]['sentvalue'],$row['total_sent'],8),8);
				$addresses[$row['address']]['sentcount']=round(bcadd($addresses[$row['address']]['sentcount'],$row['count_sent'],8),8);
			}
			if (isset($addresses[$row['address']]['receivedvalue'])) {
				$addresses[$row['address']]['receivedvalue']=round(bcadd($addresses[$row['address']]['receivedvalue'],$row['total_received'],8),8);
				$addresses[$row['address']]['receivedcount']=round(bcadd($addresses[$row['address']]['receivedcount'],$row['count_received'],8),8);

			}
		}

		$accountold_array=array();
		$update_query_set=0;
		$updatequery="UPDATE address SET account = CASE ";
		$updatequery_where=" END
							WHERE account IN (";
		foreach ($senttransactionaddress as $tx) {
			$count=0;
			$accountisset=0;
			$firstaddress="";
			$accountarray=array();
			$account="";
			$addressesintx=array();
			foreach ($tx as $address => $dummyvalue) {
				if ($count==0) {
					$firstaddress=$address;
				}
				if (isset($addresses[$address]['account'])) {
					if ($addresses[$address]['account']!="") {
						$accountisset=1;
						$accountarray[$addresses[$address]['id']]=$addresses[$address]['account'];
					}
				}
				array_push($addressesintx,$address);
				$count++;
			}
			if ($accountisset==0) {
				$account=$firstaddress;
			} else {
				ksort($accountarray);
				foreach ($accountarray as $id => $get_account) {
					$account=$get_account;
					break;
				}
				//echo $account." ";
			}

			foreach ($addressesintx as $address) {
				$addresses[$address]['account']=$account;
			}

			foreach ($addressindb as $address => $accountold) {
				if ($accountold!=$addresses[$address]['account'] && $accountold != "") {
					$updatequery.="WHEN account = '$accountold' THEN '$account' ";
					$update_query_set=1;
					array_push($accountold_array,$accountold);
				}
			}

		}

		$accountold_array=array_unique($accountold_array);
		foreach ($accountold_array as $accountold) {
			$updatequery_where.="'$accountold',";
		}

		$updatequery_where=rtrim($updatequery_where,',');
		$updatequery_where.=")";

		$updatequery=$updatequery.$updatequery_where;
		if ($update_query_set==1) {
			$result = $dbconn->query($updatequery);
		}

	}

	$inputs="";
	$update_query_set=0;
	$updatequery="UPDATE address";
	$updatebalancequery=" SET balance = CASE address ";
	$updateaccountquery=" account = CASE address ";
	$updatesenttimequery=" last_sent = CASE address ";
	$updatereceivetimequery=" last_received = CASE address ";
	$updatecountsentquery=" count_sent = CASE address ";
	$updatecountreceivequery=" count_received = CASE address ";
	$updatetotalsentquery=" total_sent = CASE address ";
	$updatetotalreceivequery=" total_received = CASE address ";
	$updatestatusquery=" status = CASE address ";
	$updatequery_where=" WHERE address IN (";
	$last_sent_set="0";
	$last_received_set="0";
	$address_unique_array=array();
	foreach ($addresses as $address => $value) {
		$update_query_set=1;
		$balancechange=$value['value'];
		if(isset($value['account'])) {
			$account=$value['account'];
		} else {
			$account=$address;
		}
		if(isset($value['senttime'])) {
			$senttime=$value['senttime'];
			$sentcount=$value['sentcount'];
			$sentvalue=$value['sentvalue'];
		} else {
			$senttime="";
		}
		if(isset($value['receivetime'])) {
			$receivetime=$value['receivetime'];
			$receivecount=$value['receivedcount'];
			$receivevalue=$value['receivedvalue'];
		} else {
			$receivetime="";
		}
		if (!array_key_exists($address,$addressindb)) {
			$inputs.="('$address', '$balancechange', '$account', '0', '$receivetime', '0', '1', '0', '$receivevalue', '0'),";
		} else {

				if ($senttime!="") {
					$last_sent_set="1";
					$updatesenttimequery.="WHEN '$address' THEN '$senttime' ";
					$updatecountsentquery.="WHEN '$address' THEN '$sentcount' ";
					$updatetotalsentquery.="WHEN '$address' THEN '$sentvalue' ";
				}
				if ($receivetime!="") {
					$last_received_set="1";
					$updatereceivetimequery.="WHEN '$address' THEN '$receivetime' ";
					$updatecountreceivequery.="WHEN '$address' THEN '$receivecount' ";
					$updatetotalreceivequery.="WHEN '$address' THEN '$receivevalue' ";
				}
				$updatebalancequery.="WHEN '$address' THEN '$balancechange' ";
				$updateaccountquery.="WHEN '$address' THEN '$account' ";
				$updatestatusquery.="WHEN '$address' THEN '0' ";
				array_push($address_unique_array,$address);
		}
	}

	$address_unique_array=array_unique($address_unique_array);
	foreach ($address_unique_array as $address) {
		$updatequery_where.="'$address',";
	}

	$updatequery_where=rtrim($updatequery_where,',');
	$updatequery_where.=")";

	if ($update_query_set==1) {
		$updatequery.=$updatebalancequery."ELSE balance END, ";
		$updatequery.=$updateaccountquery."ELSE account END, ";
		if ($last_sent_set=="1") {
			$updatequery.=$updatesenttimequery."ELSE last_sent END, ";
			$updatequery.=$updatecountsentquery."ELSE count_sent END, ";
			$updatequery.=$updatetotalsentquery."ELSE total_sent END, ";
		}
		if ($last_received_set=="1") {
			$updatequery.=$updatereceivetimequery."ELSE last_received END, ";
			$updatequery.=$updatecountreceivequery."ELSE count_received END, ";
			$updatequery.=$updatetotalreceivequery."ELSE total_received END, ";
		}
		$updatequery.=$updatestatusquery."ELSE status END ".$updatequery_where;
		$result = $dbconn->query($updatequery);
	}
	$inputs=rtrim($inputs, ",");
	$inputs.=";";
	$query="INSERT INTO address (address, balance, account, last_sent, last_received, count_sent, count_received, total_sent, total_received, status)
	VALUES".$inputs;
	$result = $dbconn->query($query);
};


function gettxinput($dbconn, $emercoin, $txid, $txdbid, $blockid, $sentaddress) {
	$rawtransaction="";
	try {
		$rawtransaction=$emercoin->getrawtransaction($txid,1);
		$values=array();
		$values["valuein"]=0;
		$values["time"]=$rawtransaction["time"];
		$coindaysdestroyed=0;
		$values["coindaysdestroyed"]=0;
		$avgcoindaysdestroyed=0;
		$values["avgcoindaysdestroyed"]=0;
		$receivetime=0;
		$values["countvin"]=0;
		$inputs="";
		foreach ($rawtransaction["vin"] as $vin) {
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
				$rawtransaction=$emercoin->getrawtransaction($vintxid,1);
				$value=$rawtransaction["vout"][$vout]["value"];
				if (isset($rawtransaction["vout"][$vout]["scriptPubKey"]["addresses"][0])) {
					$address=$rawtransaction["vout"][$vout]["scriptPubKey"]["addresses"][0];
					if (!isset($sentaddress[$address]["sent"])) {
						$sentaddress[$address]["sent"]=$value;
						$sentaddress[$address]["countsent"]=1;
					} else {
						$sentaddress[$address]["sent"]=bcadd($sentaddress[$address]["sent"],$value,8);
						$sentaddress[$address]["countsent"]++;
					}
					$sentaddress[$address]["time"]=$values["time"];
				}
				if (isset($rawtransaction["time"])) {
					$receivetime=$rawtransaction["time"];
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


			$inputs.="('$blockid', '$txdbid', '$vintxid', '$coinbase', '$vout', '$asm', '$hex', '$sequence', '$address', '$value', '$coindaysdestroyed', '$avgcoindaysdestroyed'),";
			$values["valuein"]=bcadd($values["valuein"],$value,8);
			$values["countvin"]++;
		}
		$inputs=rtrim($inputs, ",");
		$inputs.=";";
		$query="INSERT INTO vin
		(blockid, parenttxid, output_txid, coinbase, vout, asm, hex, sequence, address, value, coindaysdestroyed, avgcoindaysdestroyed)
		VALUES".$inputs;
		$result = $dbconn->query($query);
		$values["sentaddressarray"]=$sentaddress;
		return $values;
	} catch (Exception $e) {}
}

function gettxoutput($dbconn, $emercoin, $txid, $txdbid, $blockid, $time, $receiveaddress) {
	$rawtransaction="";
	try {
		$rawtransaction=$emercoin->getrawtransaction($txid,1);
		$values["valueout"]=0;
		$values["countvout"]=0;
		$inputs="";
		foreach ($rawtransaction["vout"] as $vout) {
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
				if ($reqsigs == '') {
					$reqsigs = 'NULL';
				}
			} else {
				$reqsigs = 'NULL';
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
					$receiveaddress[$address]["time"]=$time;
			}
			$inputs.="($blockid, $txdbid, '$value', $n, '$asm', '$hex', $reqsigs, '$type', '$address'),";
			$values["valueout"]=$values["valueout"]+$value;
			$values["countvout"]++;
		}
		$inputs=rtrim($inputs, ",");
		$inputs.=";";
		$query="INSERT INTO vout
		(blockid, parenttxid, value, n, asm, hex, reqsigs, type, address)
		VALUES".$inputs;
		$result = $dbconn->query($query);
		$values["receiveaddressarray"]=$receiveaddress;
		return $values;
	} catch (Exception $e) {}
}



// genesis block
$hash="00000000bcccd459d036a588d1008fce8da3754b205736f32ddfd35350e84c2d";
$query="SELECT hash FROM blocks ORDER BY id DESC LIMIT 10";
$result = $dbconn->query($query);
$dbconn->error;
while($row = $result->fetch_assoc())
{
	$hash=$row['hash'];
}
getblockinfo($dbconn, $emercoin, $hash);
?>
