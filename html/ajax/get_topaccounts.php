	<?php

		if (!empty($_COOKIE["lang"])) {
			$lang=$_COOKIE["lang"];
			require("../lang/".$lang.".php");
		} else {
			setcookie("lang","en",time()+(3600*24*14), "/");
			require("../lang/en.php");
		}

	?>

	<h3><?php echo lang('TOP_100ACCOUNTS'); ?></h3>
	<table id="address_table" class="table table-striped">
	<thead>
	<tr><th><?php echo lang('RANK_RANK'); ?></th><th><?php echo lang('ADDRESS_ADDRESS'); ?></th><th><?php echo lang('BALANCE_EMC'); ?></th><th><?php echo lang('PERCENTAGE_COINS'); ?></th><th><?php echo lang('LAST_RECEIVE'); ?></th><th><?php echo lang('LAST_SENT'); ?></th></tr>
	</thead>
	<tbody>
	<?php
if (explode('.', $_SERVER['HTTP_HOST'])[0] == "testnet") {
	require_once __DIR__ . '/../../tools/tinclude.php';
} else {
	require_once __DIR__ . '/../../tools/include.php';
}
	function TrimTrailingZeroes($nbr) {
		return strpos($nbr,'.')!==false ? rtrim(rtrim($nbr,'0'),'.') : $nbr;
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
	$block_total_coins=$_GET['total_coins'];

		$query = "SELECT account, SUM( balance ) AS balance, MAX( last_sent ) AS last_sent, MAX( last_received ) AS last_received
				FROM `address`
				GROUP BY account
				ORDER BY SUM( balance ) DESC
				LIMIT 0,100";
		$result = $dbconn->query($query);
		while($row = $result->fetch_assoc())
		{
			$account=$row['account'];
			$balance=$row['balance'];
			$last_sent=$row['last_sent'];
			$last_received=$row['last_received'];
			$accountbalancearray[$account]=$balance;
			$accountlast_sentarray[$account]=$last_sent;
			$accountlast_receivedarray[$account]=$last_received;
		}
		$countaddress=1;

		foreach ($accountbalancearray as $account => $balance) {
			$last_sent=$accountlast_sentarray[$account];
			$last_received=$accountlast_receivedarray[$account];
			if ($accountlast_sentarray[$account]=="0") {
				$time_ago_last_sent="never";
			} else {
				$last_sent=date("Y-m-d G:i:s e",$accountlast_sentarray[$account]);
				$time_ago_last_sent='<abbr title="'.$last_sent.'">'.timeAgo($accountlast_sentarray[$account]).'</abbr>';
			}
			$last_received=date("Y-m-d G:i:s e",$accountlast_receivedarray[$account]);
			$last_received_time=$accountlast_receivedarray[$account];
			//change account name to Top100 address name

			if(isset($addresstoaccount[$account])) {
				$account=$addresstoaccount[$account];
			}

			echo '<tr><td>'.$countaddress.'</td><td><a href="/address/'.$account.'"><button type="button" class="btn btn-link" style="padding:0">'.$account.'</button></a></td><td>'.TrimTrailingZeroes(number_format(round($balance,7),8)).'</td><td>'.TrimTrailingZeroes(number_format(bcdiv(bcmul($balance,100,6),$block_total_coins,2),2)).'</td><td><abbr title="'.$last_received.'">'.timeAgo($last_received_time).'</abbr></td><td>'.$time_ago_last_sent.'</td></td></tr>';
			if ($countaddress==100) {break;}
			$countaddress++;
		}
	?>
	</tbody>
	</table>
