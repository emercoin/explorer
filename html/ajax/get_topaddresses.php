	<?php
	
		if (!empty($_COOKIE["lang"])) {
			$lang=$_COOKIE["lang"];
			require("../lang/".$lang.".php");
		} else {
			setcookie("lang","en",time()+(3600*24*14), "/");
			require("../lang/en.php");
		}

	?>
	
	<h3><?php echo lang('TOP_100ADDRESSES'); ?></h3>
	<table id="address_table" class="table table-striped">
	<thead>
	<tr><th><?php echo lang('RANK_RANK'); ?></th><th><?php echo lang('ADDRESS_ADDRESS'); ?></th><th><?php echo lang('BALANCE_EMC'); ?></th><th><?php echo lang('PERCENTAGE_COINS'); ?></th><th><?php echo lang('LAST_RECEIVE'); ?></th><th><?php echo lang('LAST_SENT'); ?></th></tr>
	</thead>
	<tbody>
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
	function TrimTrailingZeroes($nbr) {
		return strpos($nbr,'.')!==false ? rtrim(rtrim($nbr,'0'),'.') : $nbr;
	}
	function timeAgo ($time) {
		$time = time() - $time;

		$tokens = array (
			86400 => lang('DAYS_DAYS'),
			3600 => lang('HOURS_HOURS'),
			60 => lang('MINUTES_MINUTES'),
			1 => lang('SECONDS_SECONDS')
		);

		foreach ($tokens as $unit => $text) {
			if ($time < $unit) continue;
			$numberOfUnits = floor($time / $unit);
			return $numberOfUnits.' '.$text.(($numberOfUnits>1)?'':'');
		}
	}	
	$block_total_coins=$_GET['total_coins'];
	
		$countaddress=1;
		$query = "SELECT address, balance, last_sent, last_received, account
		FROM address
		ORDER BY balance DESC
		LIMIT 0,100";
		$result = $dbconn->query($query);
		$addresstoaccount=array();
		while($row = $result->fetch_assoc())
		{
			$address=$row['address'];
			$balance=$row['balance'];
			if (!isset($addresstoaccount[$row['account']])) {
				$addresstoaccount[$row['account']]=$address;
			}
			if ($row['last_sent']=="0") {
				$time_ago_last_sent=lang('NEVER_NEVER');
			} else {
				$last_sent=date("Y-m-d G:i:s e",$row['last_sent']);
				$time_ago_last_sent='<abbr title="'.$last_sent.'">'.timeAgo($row['last_sent']).'</abbr>';
			}
			$last_received=date("Y-m-d G:i:s e",$row['last_received']);
			echo '<tr><td>'.$countaddress.'</td><td><a href="/address/'.$address.'"><button type="button" class="btn btn-link" style="padding:0">'.$address.'</button></a></td><td>'.TrimTrailingZeroes(number_format(round($balance,7),8)).'</td><td>'.TrimTrailingZeroes(number_format(bcdiv(bcmul($balance,100,6),$block_total_coins,2),2)).'</td><td><abbr title="'.$last_received.'">'.timeAgo($row['last_received']).'</abbr></td><td>'.$time_ago_last_sent.'</td></td></tr>';
			$countaddress++;
		}
	?>
	</tbody>
	</table>