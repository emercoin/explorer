<?php
if (!empty($_COOKIE["network"])) {
	$network=$_COOKIE["network"];
	if ($network=='Mainnet') {
		require_once __DIR__ . '/../../tools/include.php';
	} else if ($network=='Testnet') {
		require_once __DIR__ . '/../../tools/tinclude.php';
	}
} else {
	setcookie("network","Mainnet",time()+(3600*24*14), "/");
	require_once __DIR__ . '/../tools/include.php';
}
$uriaddress=$_GET['address'];

if (!empty($_COOKIE["lang"])) {
	$lang=$_COOKIE["lang"];
	require("../lang/".$lang.".php");
} else {
	setcookie("lang","en",time()+(3600*24*14), "/");
	require("../lang/en.php");
}

function TrimTrailingZeroes($nbr) {
    return strpos($nbr,'.')!==false ? rtrim(rtrim($nbr,'0'),'.') : $nbr;
}

$query = "SELECT total_coins FROM blocks ORDER BY height DESC LIMIT 1";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$block_total_coins=$row['total_coins'];
	}


$query="SELECT account FROM address WHERE address='$uriaddress'";	
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$account=$row['account'];
	}
$addressbalances=array();
$balancetotal=0;
if (isset($account)) {
$query="SELECT address, balance FROM address WHERE account='$account'";	
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$address=$row['address'];
		$balance=$row['balance'];
		$addressbalances[$address]=$balance;
		$balancetotal+=$balance;
	}	
}	
arsort($addressbalances);

echo'
<h4><strong>'.lang("ESTIMATED_ACCVALUE").'</strong></h4>
<table class="table">
	<tr><td><h3>'.lang("BALANCE_BALANCE").'</h3></td><td width="75%"><h3><span class="label label-success">'.TrimTrailingZeroes(number_format(round($balancetotal,7),8)).' EMC</span></h3></td></tr>';
foreach ($addressbalances as $address => $value) {
	if ($value>0) {echo '<tr><td><a href="/address/'.$address.'"><button type="button" class="btn btn-link" style="padding:0">'.$address.'</button></a></td><td>'.TrimTrailingZeroes(number_format(round($value,7),8))." EMC</td></tr>";}
}
echo '</table>';
echo '<button class="btn btn-xs btn-primary" type="button" data-toggle="collapse" data-target="#unusedAddresses" aria-expanded="false" aria-controls="unusedAddresses">
  '.lang("SHOW_ADDRESSES").'
</button>
<div class="collapse" id="unusedAddresses">
<p>
<table class="table">';
foreach ($addressbalances as $address => $value) {
	if ($value==0) {echo '<tr><td><a href="/address/'.$address.'"><button type="button" class="btn btn-link" style="padding:0">'.$address.'</button></a></td><td></td></tr>';}
}
echo '</table>
</div>';

?>