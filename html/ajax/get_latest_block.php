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
$block_height="";
$block_hash="";
$block_total_coins="";
$block_numtx="0";
$block_valueout="0";
$txSkipValue = 1;
$confirmedBy = "";

$block_hash=$emercoin->getbestblockhash();
$block=$emercoin->getblock($block_hash);

$flags=$block['flags'];
if ($flags == "proof-of-stake") {
	$txSkipValue = 2;
}
$time = $flags=$block['time'];
$emc_info=$emercoin->getinfo();
$block_total_coins=$emc_info['moneysupply'];
$block_height=$emc_info['blocks'];
$txs=$block['tx'];
foreach ($txs as $tx) {
	$tx_full=$emercoin->getrawtransaction($tx,1);
	$block_numtx++;
	foreach ($tx_full['vout'] as $vout) {
		if (isset($vout['scriptPubKey'])) {
			$asm = $vout['scriptPubKey']['asm'];
			if ($asm != "") {
				if (strpos($asm, 'OP_DUP OP_HASH160') !== false) {
				} else {
					$confirmedBy = $vout['scriptPubKey']['addresses'][0];
				}
			}
		}
	}
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
	echo '
	<div class="panel-body">
		<span class="lead">'.lang('LATEST_BLOCK').': <a href=/block/'.$block_hash.'>'.$block_height.'</a></span><br>
		'.lang('CONFIRMED_TRANSACTIONS').': '.$block_numtx.'<br>
		'.lang('TRANSACTION_VOLUME').': '.TrimTrailingZeroes(number_format($block_valueout,6)).' EMC</p>
		<small><span class="text-muted">
			confirmed by <a href="/address/'.$confirmedBy.'">'.$confirmedBy.'</a><br>
			'.date("Y-m-d H:i:s", $time).'
		</span></small>
	</div>';

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
