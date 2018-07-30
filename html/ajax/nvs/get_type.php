	<?php
	ini_set("display_errors", 1);
	error_reporting(-1);

if (explode('.', $_SERVER['HTTP_HOST'])[0] == "testnet") {
	require_once __DIR__ . '/../../../tools/tinclude.php';
} else {
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
