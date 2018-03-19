<?php
if (isset($_SERVER['REQUEST_URI'])) {
	$URI=explode('/',$_SERVER['REQUEST_URI']);
	if ($URI[1]=="account") {
		if (isset($URI[2])) {
			$uriaddress=$URI[2];
		}
	}
}

function TrimTrailingZeroes($nbr) {
    return strpos($nbr,'.')!==false ? rtrim(rtrim($nbr,'0'),'.') : $nbr;
}
$query="SELECT DISTINCT tx.txid, vin.address
FROM vin
INNER JOIN transactions AS tx ON tx.id = vin.parenttxid
AND tx.numvin > '0'
WHERE vin.address != ''
ORDER BY vin.parenttxid";
$result = $dbconn->query($query);
$accounts=array();
while($row = $result->fetch_assoc())
{
	$address=$row['address'];
	$txid=$row['txid'];
	if (!isset($accounts[$txid])) {
		$accounts[$txid]=$address;
	} else {
		$accounts[$txid].=",".$address;
	}
}	

$accountaddresses=array();
foreach($accounts as $key => $value) {
    if (strpos($value, $uriaddress) !== false) {
		foreach (explode(",",$value) as $val) {
			array_push($accountaddresses,$val);
		}	
    }
}
$accountaddresses=array_unique($accountaddresses);
$accountaddresses2=array();
foreach ($accountaddresses as $address) {
	foreach ($accounts as $key => $value) {
		if (strpos($value, $address) !== false) {
			foreach (explode(",",$value) as $val) {
				array_push($accountaddresses2,$val);
			}	
		}
	}	
}
$accountaddresses2=array_unique($accountaddresses2);

if (count($accountaddresses2)==0) {
	array_push($accountaddresses2,$uriaddress);
}

$query = "SELECT vin.address AS address, SUM(vin.value) AS sent_total, '' AS received_total, MAX(tx.time) AS time
FROM vin
INNER JOIN transactions AS tx
ON tx.id=vin.parenttxid
GROUP BY vin.address
UNION
SELECT vout.address AS address, '' AS sent_total, SUM(vout.value) AS received_total, MAX(tx.time) AS time
FROM vout
INNER JOIN transactions AS tx
ON tx.id=vout.parenttxid
GROUP BY vout.address
ORDER BY address";
	$result = $dbconn->query($query);
	$addresses=array();
	$addressestime=array();
	while($row = $result->fetch_assoc())
	{
		$address=$row['address'];
		if (!isset($addresses[$address])) {
			$addresses[$address]=0;
		}
		if ($row['sent_total']!="") {
			$addresses[$address]=bcsub($addresses[$address],$row['sent_total'],8);
		}
		if ($row['received_total']!="") {
			$addresses[$address]=bcadd($addresses[$address],$row['received_total'],8);
		}
		if (!isset($addressestime[$address])) {
			$addressestime[$address]=0;
		}
		if ($addressestime[$address]<$row['time']) {
			$addressestime[$address]=$row['time'];
		}
	}
	arsort($addresses);
//	print_r($addresses);

$query = "SELECT total_coins FROM blocks ORDER BY height DESC LIMIT 1";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$block_total_coins=$row['total_coins'];
	}

$balance=0;	
$addressbalances=array();
foreach	($accountaddresses2 as $address) {
	
	$addressbalances[$address]=$addresses[$address];
	$balance+=$addresses[$address];
}	
arsort($addressbalances);

echo'
<h4><strong>Estimated Account Value</strong></h4>
<table class="table">
	<tr><td><h3>Balance</h3></td><td width="75%"><h3><span class="label label-success">'.TrimTrailingZeroes(number_format($balance,8)).' EMC</span></h3></td></tr>';
foreach ($addressbalances as $key => $value) {
	if ($value>0) {echo '<tr><td>'.$key.'</td><td>'.$value." EMC</td></tr>";}
}
echo '</table>';
echo '<button class="btn btn-xs btn-primary" type="button" data-toggle="collapse" data-target="#unusedAddresses" aria-expanded="false" aria-controls="unusedAddresses">
  Show unused addresses
</button>
<div class="collapse" id="unusedAddresses">
<p>
<table class="table">';
foreach ($addressbalances as $key => $value) {
	if ($value==0) {echo '<tr><td>'.$key.'</td><td></td></tr>';}
}
echo '</table>
</div>';

?>