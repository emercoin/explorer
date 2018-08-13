	<?php
if (explode('.', $_SERVER['HTTP_HOST'])[0] == "testnet") {
	require_once __DIR__ . '/../../tools/tinclude.php';
} else {
	require_once __DIR__ . '/../../tools/include.php';
}

if (!empty($_COOKIE["lang"])) {
	$lang=$_COOKIE["lang"];
	require("../lang/".$lang.".php");
} else {
	setcookie("lang","en",time()+(3600*24*14), "/");
	require("../lang/en.php");
}

$query = "SELECT COUNT(id) AS posblocks FROM (
			SELECT id, flags FROM blocks
			ORDER BY height DESC
			LIMIT 1000) AS PoS_blocks
		WHERE flags LIKE '%proof-of-stake%'";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$pos_dominance = bcdiv($row['posblocks'],10,1);
	}

	echo '
	<div class="panel-body">
		<span class="lead">PoS Dominance</span><br>
		<div class="progress">
		  <div class="progress-bar" role="progressbar" aria-valuenow="'.$pos_dominance.'" aria-valuemin="0" aria-valuemax="100" style="width: '.$pos_dominance.'%;">
		    '.$pos_dominance.'%
		  </div>
		</div>
	</div>';
?>
