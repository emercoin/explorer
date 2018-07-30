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

	echo '<div class="panel-heading"><b>Emercoin Versions</b> - Get the newest version <a target=_blank href="http://emercoin.com/#download">here</a></div>
	<table class="table">
	<thead>
	<tr><th>Version</th><th>Share</th></tr>
	</thead>
	<tbody>';
	$barcount=0;
	$emc_info=$emercoin->getinfo();
	$block_height=$emc_info['blocks'];
	$lastBlocks = 1000;
	$count062 = 0;
	$count062mm = 0;
	$count063 = 0;
	$count063mm = 0;
	$count070 = 0;
	$count070mm = 0;
	$count071 = 0;
	$count071mm = 0;
	for ($conf = 0;  $conf < $lastBlocks; $conf++) {
		$block_hash=$emercoin->getblockhash($block_height-$conf);
		$block=$emercoin->getblock($block_hash);
		$version=$block['version'];
		if ($version == 43646981) {
			$count062++;
		}
		if ($version == 43647237) {
			$count062mm++;
		}
		if ($version == 43646982) {
			$count063++;
		}
		if ($version == 43647238) {
			$count063mm++;
		}
		if ($version == 43646983) {
			$count070++;
		}
		if ($version == 43647239) {
			$count070mm++;
		}
		if ($version == 43646984) {
			$count071++;
		}
		if ($version == 43647240) {
			$count071mm++;
		}
	}

	if ($count062 != 0) {
		$count062 = bcmul(bcdiv($count062,$lastBlocks,4),100,2);
		echo '<tr><td>0.6.2</td><td>'.$count062.' %</td></tr>';
	}
	if ($count062mm != 0) {
		$count062mm = bcmul(bcdiv($count062mm,$lastBlocks,4),100,2);
		echo '<tr><td>0.6.2 (Merged Mining)</td><td>'.$count062mm.' %</td></tr>';
	}
	if ($count063 != 0) {
		$count063 = bcmul(bcdiv($count063,$lastBlocks,4),100,2);
		echo '<tr><td>0.6.3</td><td>'.$count063.' %</td></tr>';
	}
	if ($count063mm != 0) {
		$count063mm = bcmul(bcdiv($count063mm,$lastBlocks,4),100,2);
		echo '<tr><td>0.6.3 (Merged Mining)</td><td>'.$count063mm.' %</td></tr>';
	}
	if ($count070 != 0) {
		$count070 = bcmul(bcdiv($count070,$lastBlocks,4),100,2);
		echo '<tr><td>0.7.0</td><td>'.$count070.' %</td></tr>';
	}
	if ($count070mm != 0) {
		$count070mm = bcmul(bcdiv($count070mm,$lastBlocks,4),100,2);
		echo '<tr><td>0.7.0 (Merged Mining)</td><td>'.$count070mm.' %</td></tr>';
	}
	if ($count071 != 0) {
		$count071 = bcmul(bcdiv($count071,$lastBlocks,4),100,2);
		echo '<tr><td>0.7.1</td><td>'.$count071.' %</td></tr>';
	}
	if ($count071mm != 0) {
		$count071mm = bcmul(bcdiv($count071mm,$lastBlocks,4),100,2);
		echo '<tr><td>0.7.1 (Merged Mining)</td><td>'.$count071mm.' %</td></tr>';
	}
?>
</tbody></table>
