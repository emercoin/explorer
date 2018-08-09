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
$block_hash="";
$block_total_coins="";
$pow_difficulty="";
$pos_difficulty="";

$block_hash=$emercoin->getbestblockhash();
$block=$emercoin->getblock($block_hash);

$flags=$block['flags'];
if ($flags == "proof-of-stake") {
	$txSkipValue = 2;
	$pos_difficulty = $block['difficulty'];
}
$emc_info=$emercoin->getinfo();
$pow_difficulty = $emc_info['difficulty'];
$block_total_coins=$emc_info['moneysupply'];

while ($flags != "proof-of-stake") {
	$oldBlock = $emercoin->getblock($block['previousblockhash']);
	$block = $oldBlock;
	$flags=$block['flags'];
	if ($flags == "proof-of-stake") {
		$pos_difficulty = $block['difficulty'];
	}
}

function TrimTrailingZeroes($nbr) {
    return strpos($nbr,'.')!==false ? rtrim(rtrim($nbr,'0'),'.') : $nbr;
}
	echo '
	<div class="panel-body">
		<span class="lead">'.lang('COINS_AVAILABLE').'</span><br>'.TrimTrailingZeroes(number_format($block_total_coins,6)).' EMC<br><hr>
		<span class="lead">'.lang('POW_DIFFICULTY').'</span><br>'.TrimTrailingZeroes(number_format($pow_difficulty,8)).'<br><hr>
		<span class="lead">'.lang('POS_DIFFICULTY').'</span><br>'.TrimTrailingZeroes(number_format($pos_difficulty,8)).'<br>
	</div>';
?>
