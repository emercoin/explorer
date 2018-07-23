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


		$block_height="";
		$block_hash="";
		$block_total_coins="";
		$block_numtx="0";
		$block_valueout="0";
		$pow_difficulty="";
		$pos_difficulty="";
		$difficultyFlag = "PoW";
		$txSkipValue = 1;

$block_hash=$emercoin->getbestblockhash();
$block=$emercoin->getblock($block_hash);
$difficulty=$block['difficulty'];
$flags=$block['flags'];
if ($flags == "proof-of-stake") {
	$difficultyFlag = "PoS";
	$txSkipValue = 2;
}
$emc_info=$emercoin->getinfo();
$block_total_coins=$emc_info['moneysupply'];
$block_height=$emc_info['blocks'];
$txs=$block['tx'];
foreach ($txs as $tx) {
	$tx_full=$emercoin->getrawtransaction($tx,1);
	$block_numtx++;
	foreach ($tx_full['vin'] as $vin) {
		if (isset($vin['txid'])){
			if ($block_numtx > $txSkipValue) {
				$block_valueout+=getTX_vout_value($emercoin, $vin['txid'], $vin['vout']);
			}
		}
	}
}
$block_numtx = $block_numtx-$txSkipValue;

function TrimTrailingZeroes($nbr) {
    return strpos($nbr,'.')!==false ? rtrim(rtrim($nbr,'0'),'.') : $nbr;
}
			echo '<h3><strong>'.lang('WELCOME_EXPLORER').'</strong></h3>';
			echo '<p>'.lang('LATEST_BLOCK').': <a href=/block/'.$block_hash.'>'.$block_height.'</a><br>';
			echo lang('CONFIRMED_TRANSACTIONS').': '.$block_numtx.'<br>';
			echo lang('TRANSACTION_VOLUME').': '.TrimTrailingZeroes(number_format($block_valueout,6)).' EMC</p>';
			echo '<p>'.lang('COINS_AVAILABLE').': '.TrimTrailingZeroes(number_format($block_total_coins,6)).' EMC<br>';
			echo $difficultyFlag." ".lang('DIFFICULTY_DIFFICULTY').': '.TrimTrailingZeroes(number_format($difficulty,8)).'<br>';
			echo '<p><a class="btn btn-primary btn-lg" href="/chain" role="button">'.lang('EXPLORE_EXPLORE').'</a></p>';
			
function getTX_vout_value($emercoin, $txHash, $n) {
	$tx=$emercoin->getrawtransaction($txHash,1);
	$tx_vout=$tx['vout'];
	foreach ($tx_vout as $vout) {
		if ($vout['n'] == $n) {
			return $vout['value'];
		}
	}
}
?>
