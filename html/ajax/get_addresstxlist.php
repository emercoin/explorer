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
if (!empty($_COOKIE["lang"])) {
	$lang=$_COOKIE["lang"];
	require("../lang/".$lang.".php");
} else {
	setcookie("lang","en",time()+(3600*24*14), "/");
	require("../lang/en.php");
}

	$query = "SELECT * FROM blocks ORDER BY height DESC LIMIT 1";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$block_height=$row['height'];
		$block_hash=$row['hash'];
		$block_total_coins=$row['total_coins'];
		$block_numtx=$row['numtx'];
		$block_valueout=$row['valueout'];
	}
	
	$query = "SELECT difficulty FROM blocks WHERE flags LIKE '%proof-of-work%' ORDER BY height DESC LIMIT 1";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$pow_difficulty=$row['difficulty'];
	}
	
	$query = "SELECT difficulty FROM blocks WHERE flags LIKE '%proof-of-stake%' ORDER BY height DESC LIMIT 1";
	$result = $dbconn->query($query);
	$pos_difficulty=1;
	while($row = $result->fetch_assoc())
	{
		$pos_difficulty=$row['difficulty'];
	}

function TrimTrailingZeroes($nbr) {
    return strpos($nbr,'.')!==false ? rtrim(rtrim($nbr,'0'),'.') : $nbr;
}
			echo '<h2><strong>'.lang('WELCOME_EXPLORER').'</strong></h2>';
			echo '<p>'.lang('LATEST_BLOCK').': <a href=/block/'.$block_hash.'>'.$block_height.'</a><br>';
			echo lang('CONFIRMED_TRANSACTIONS').': '.$block_numtx.'<br>';
			echo lang('TRANSACTION_VOLUME').': '.TrimTrailingZeroes(number_format($block_valueout,6)).' EMC</p>';
			echo '<p>'.lang('COINS_AVAILABLE').': '.TrimTrailingZeroes(number_format($block_total_coins,6)).' EMC<br>';
			echo lang('POW_DIFFICULTY').': '.TrimTrailingZeroes(number_format($pow_difficulty,8)).'<br>';
			echo lang('POS_DIFFICULTY').': '.TrimTrailingZeroes(number_format($pos_difficulty,8)).'</p>';
			echo '<p><a class="btn btn-primary btn-lg" href="/chain" role="button">'.lang('EXPLORE_EXPLORE').'</a></p>';
?>