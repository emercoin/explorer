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

	if (!empty($_COOKIE["network"])) {
	$network=$_COOKIE["network"];
	if ($network=='Mainnet') {
		require_once __DIR__ . '/../../tools/include.php';
	} else if ($network=='Testnet') {
		require_once __DIR__ . '/../../tools/tinclude.php';
	}
} else {
	setcookie("network","Mainnet",time()+(3600*24*14), "/");
	require_once __DIR__ . '/../../tools/include.php';
}

	echo '<div class="panel-heading"><b>'.lang('RECENT_TRANSACTIONS').'</b></div>
	<table class="table">
	<thead>
	<tr><th>'.lang('TX_ID').'</th><th>'.lang('VALUE_EMC').'</th><th></th></tr>
	</thead>
	<tbody>';
	$showBlocksQuery = "SELECT
			t.txid,
			t.valueout,
			(c.blockid-t.blockid)+1 AS confirmations
		FROM transactions t
		JOIN (SELECT DISTINCT blockid
				FROM transactions
				WHERE fee > 0
				ORDER BY blockid DESC
				LIMIT 9) AS b
		ON b.blockid = t.blockid AND t.fee > 0
		CROSS JOIN (SELECT blockid
				FROM transactions
				WHERE fee > 0
				ORDER BY blockid DESC
				LIMIT 1) AS c
		WHERE (c.blockid-t.blockid)+1 <= 9
		ORDER BY t.blockid DESC";
	$result = $dbconn->query($showBlocksQuery);
	while($row = $result->fetch_assoc())
	{
		$txid=$row['txid'];
		$tx_id_short = substr($txid, 0, 4)."...".substr($txid, -4);
		$confirmations=$row['confirmations'];
		if ($confirmations<3) {$labelcolor="danger";};
		if ($confirmations>=3 && $confirmations<6) {$labelcolor="warning";};
		if ($confirmations>=6) {$labelcolor="success";};
		echo '<tr><td><a href="/tx/'.$txid.'" class="btn btn-primary btn-xs" role="button">'.$tx_id_short.'</a></td><td>'.$row["valueout"].'</td><td><span class="label label-'.$labelcolor.'">'.$confirmations.'</span></td></tr>';
	}
	echo "</tbody></table>";

	?>
