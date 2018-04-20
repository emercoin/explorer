	<?php
	ini_set("display_errors", 1);
	error_reporting(-1);

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
$typeArray=[];
	$query = "SELECT DISTINCT type
		FROM nvs
		WHERE expires_at > (SELECT height FROM blocks ORDER BY height DESC LIMIT 1)";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		array_push($typeArray, $row['type']);
	}
echo json_encode($typeArray);
?>
