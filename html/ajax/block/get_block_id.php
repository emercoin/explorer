	<?php
	ini_set("display_errors", 1);
	error_reporting(-1);

if (explode('.', $_SERVER['HTTP_HOST'])[0] == "testnet") {
	require_once __DIR__ . '/../../../tools/tinclude.php';
} else {
	require_once __DIR__ . '/../../../tools/include.php';
}

$hash=mysqli_real_escape_string($dbconn, $_GET['hash']);

if (isset($hash) && $hash!="") {
	if (is_numeric($hash)) {
		$query = "SELECT hash FROM blocks WHERE height = '$hash'";
		$result = $dbconn->query($query);
		while($row = $result->fetch_assoc())
		{
			$hash=$row['hash'];
		}
	}
	$query = "SELECT id
		FROM blocks
		WHERE hash = '$hash'";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$block_id=$row['id'];
	}
	if (isset($block_id)) {
		echo $block_id;
	} else {
		echo '-1';
	}
}
?>
