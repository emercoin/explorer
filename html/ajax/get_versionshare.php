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
	require_once __DIR__ . '/../tools/include.php';
}

	echo '<div class="panel-heading"><b>Emercoin Versions</b> - Get the newest version <a target=_blank href="http://emercoin.com/#download">here</a></div>
	<table class="table">
	<thead>
	<tr><th>Version</th><th>Share</th></tr>
	</thead>
	<tbody>';
	$barcount=0;
	$blockinfo=$emercoin->getinfo();
	$blockheight=bcsub($blockinfo['blocks'],999,0);
	$showBlocksQuery = "SELECT COUNT(*) as count, version FROM blocks
						WHERE height >= '".$blockheight."'
						GROUP BY version
						ORDER BY height DESC";
	$result = $dbconn->query($showBlocksQuery);
	while($row = $result->fetch_assoc())
	{
		$count=bcmul(bcdiv($row['count'],1000,4),100,2);
		$version=$row['version'];
		if ($version == 43646981) {
			$version="0.6.2";
			$barcount+=$count;
		}
		if ($version == 43647237) {
			$version="0.6.2 (Merged Mining)";
			$barcount+=$count;
		}
		if ($version == 43646982) {
			$version="0.6.3";
			$barcount+=$count;
		}
		if ($version == 43647238) {
			$version="0.6.3 (Merged Mining)";
			$barcount+=$count;
		}
		if ($version == 43646983) {
			$version="0.6.4";
			$barcount+=$count;
		}
		if ($version == 43647239) {
			$version="0.6.4 (Merged Mining)";
			$barcount+=$count;
		}
		echo '<tr><td>'.$version.'</td><td>'.$count.' %</td></tr>';
	}
		?>
	</tbody></table>
