<?php

	// get current date
	$date=time();
	$date_24=($date-86400);
	$query="SELECT COUNT(id) AS blocks, SUM(size) as size_24, SUM(fee) as fees, SUM(mint) as minted, SUM(numtx) as transactions, SUM(valuein) as tx_in, SUM(valueout) as tx_out, SUM(coindaysdestroyed) as coin_dest
	FROM blocks WHERE time >= '$date_24'";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$blocks=$row['blocks'];
		$size_24=$row['size_24'];
		$unit_24="B";
		if ($size_24 >= 1024 && $size_24 < 1024000) {
			$unit_24="KiB";
			$size_24=bcdiv($size_24,1024,2);
		} elseif ($size_24 >= 1024000) {
			$unit_24="MiB";
			$size_24=bcdiv($size_24,1024000,2);
		}
		$minted=$row['minted'];
		$transactions=$row['transactions'];
		$fees=$row['fees'];
		$tx_in=$row['tx_in'];
		$tx_out=$row['tx_out'];
		$coin_dest=$row['coin_dest'];
	}

	$query="SELECT COUNT(id) AS pos
	FROM blocks WHERE time >= '$date_24' AND flags LIKE '%proof-of-stake%'";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$pos=$row['pos'];
	}

	$query="SELECT COUNT(txid) as transactions,
	SUM(valuein) as input,
	SUM(valueout) AS output,
	SUM(coindaysdestroyed) AS coindaysdestroyed,
	(SUM(coindaysdestroyed)/SUM(valuein)) AS avgcoindaysdestroyed,
	SUM(fee) AS fees
	FROM `transactions`
	WHERE time >=  '$date_24' AND fee > '0'";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$wo_tx_in=$row['input'];
		$wo_coin_dest=$row['coindaysdestroyed'];
		$wo_avg_coin_dest=$row['avgcoindaysdestroyed'];
		$wo_tx_out=$row['output'];
		$wo_fees=$row['fees'];
	}

	$query="SELECT tx.time, vin.parenttxid as txid
		FROM transactions AS tx
		INNER JOIN vin ON vin.parenttxid = tx.id AND vin.coinbase=''
		WHERE tx.time >= '$date_24' AND tx.fee > '0'
		UNION
		SELECT tx.time, vout.parenttxid as txid
		FROM transactions AS tx
		INNER JOIN vout ON vout.parenttxid = tx.id
		WHERE tx.time >= '$date_24' AND tx.fee > '0'
		ORDER BY time";
	$result = $dbconn->query($query);
	$wo_totaltx=0;
	while($row = $result->fetch_assoc())
	{
		$wo_totaltx++;
	}

function TrimTrailingZeroes($nbr) {
    return strpos($nbr,'.')!==false ? rtrim(rtrim($nbr,'0'),'.') : $nbr;
}
?>


<div class="container">



	<p>
		<?php
	$query="SELECT height, total_addresses_used, total_addresses_unused, total_coins, total_avgcoindays
	FROM blocks ORDER BY height DESC LIMIT 1";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$height=$row['height'];
		$total_addresses_used=$row['total_addresses_used'];
		$total_addresses_unused=$row['total_addresses_unused'];
		$total_coins=$row['total_coins'];
		$total_avgcoindays=$row['total_avgcoindays'];
	}

	$query="SELECT SUM(size) AS size, SUM(numtx) AS alltransactions
	FROM blocks";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$size=$row['size'];
		$alltransactions=$row['alltransactions'];
		$unit="B";
		if ($size >= 1024 && $size < 1024000) {
			$unit="KiB";
			$size=bcdiv($size,1024,2);
		} elseif ($size >= 1024000) {
			$unit="MiB";
			$size=bcdiv($size,1024000,2);
		}
	}

	$query = "SELECT difficulty FROM blocks WHERE flags LIKE '%proof-of-work%' ORDER BY height DESC LIMIT 1";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$current_pow_difficulty=$row['difficulty'];
	}

	$query = "SELECT difficulty FROM blocks WHERE flags LIKE '%proof-of-stake%' ORDER BY height DESC LIMIT 1";
	$result = $dbconn->query($query);
	$pos_difficulty=1;
	while($row = $result->fetch_assoc())
	{ 
		$current_pos_difficulty=$row['difficulty'];
	}
	$date_1day=($date-86400);
	$query = "SELECT COUNT( id ) AS blocks, AVG(difficulty) AS difficulty
	FROM `blocks`
	WHERE time >= '$date_1day' AND flags = 'proof-of-work'";
	$result = $dbconn->query($query);
	$current_pow_hashrate=0;
	while($row = $result->fetch_assoc())
	{
		$pow_difficulty=$row['difficulty'];
		$pow_blocks=$row['blocks'];
		if ($pow_blocks>0) {
			$block_interval=bcdiv(86400,$pow_blocks,8);
		} else {
			$block_interval=0;
		}
		
		if ($block_interval>0) {
			$current_pow_hashrate=bcdiv(bcmul($pow_difficulty,bcpow(2,32,8),8),$block_interval,8);
		} else {
			$current_pow_hashrate=0;
		}
		$current_pow_hashrate=bcdiv($current_pow_hashrate,1000000000000,8); //to THash
	}

	$date_14days=($date-1209600);
	$query="SELECT (
		YEAR( FROM_UNIXTIME( `time` ) ) *3650 + MONTH( FROM_UNIXTIME( `time` ) ) *120 + DAY( FROM_UNIXTIME( `time` ) )
		) AS `day` , FROM_UNIXTIME( time ) AS time, MAX( height ) AS height, MAX( total_coins ) AS mint, SUM(size) AS size, SUM(numtx) AS tx, AVG(total_addresses_used) AS total_addresses_used, AVG(total_addresses_unused) AS total_addresses_unused, (AVG(total_addresses_used)+AVG(total_addresses_unused)) AS total_addresses, AVG(total_avgcoindays) AS total_avgcoindays, MAX( id ) AS id
		FROM `blocks`
		WHERE time >= '$date_14days'
		GROUP BY (
		YEAR( FROM_UNIXTIME( `time` ) ) *3650 + MONTH( FROM_UNIXTIME( `time` ) ) *120 + DAY( FROM_UNIXTIME( `time` ) )
		)
		ORDER BY time";
	$result = $dbconn->query($query);
	$count=0;
	$regrassionArray_height=array();
	$values_height=array();
	$regrassionArray_mint=array();
	$values_mint=array();
	$regrassionArray_size=array();
	$values_size=array();
	$regrassionArray_tx=array();
	$values_tx=array();
	$regrassionArray_total_addresses_used=array();
	$values_total_addresses_used=array();
	$regrassionArray_total_addresses_unused=array();
	$values_total_addresses_unused=array();
	$regrassionArray_total_addresses=array();
	$values_total_addresses=array();
	$regrassionArray_total_avgcoindays=array();
	$values_total_avgcoindays=array();
	$size_sum=0;
	$tx_sum=0;
	while($row = $result->fetch_assoc())
	{
		if ($count==0) {
			$values_height['firstXvalue']=$row['id'];
			$values_mint['firstXvalue']=$row['id'];
			$values_size['firstXvalue']=$row['id'];
			$values_tx['firstXvalue']=$row['id'];
			$values_total_addresses_used['firstXvalue']=$row['id'];
			$values_total_addresses_unused['firstXvalue']=$row['id'];
			$values_total_addresses['firstXvalue']=$row['id'];
			$values_total_avgcoindays['firstXvalue']=$row['id'];
		}
		$count++;
		$regrassionArray_height[$count]['x']=$count;
		$regrassionArray_height[$count]['y']=$row['height'];
		$regrassionArray_mint[$count]['x']=$count;
		$regrassionArray_mint[$count]['y']=$row['mint'];
		$regrassionArray_size[$count]['x']=$count;
		$size_sum=bcadd($size_sum,$row['size']);
		$regrassionArray_size[$count]['y']=$size_sum;
		$regrassionArray_tx[$count]['x']=$count;
		$tx_sum=bcadd($tx_sum,$row['tx']);
		$regrassionArray_tx[$count]['y']=$tx_sum;
		$regrassionArray_total_addresses_used[$count]['x']=$count;
		$regrassionArray_total_addresses_used[$count]['y']=$row['total_addresses_used'];
		$regrassionArray_total_addresses_unused[$count]['x']=$count;
		$regrassionArray_total_addresses_unused[$count]['y']=$row['total_addresses_unused'];
		$regrassionArray_total_addresses[$count]['x']=$count;
		$regrassionArray_total_addresses[$count]['y']=$row['total_addresses'];
		$regrassionArray_total_avgcoindays[$count]['x']=$count;
		$regrassionArray_total_avgcoindays[$count]['y']=$row['total_avgcoindays'];
		$timestamp=$row['id'];
	}
	$values_height['lastXvalue']=$timestamp;
	$values_mint['lastXvalue']=$timestamp;
	$values_size['lastXvalue']=$timestamp;
	$values_tx['lastXvalue']=$timestamp;
	$values_total_addresses_used['lastXvalue']=$timestamp;
	$values_total_addresses_unused['lastXvalue']=$timestamp;
	$values_total_addresses['lastXvalue']=$timestamp;
	$values_total_avgcoindays['lastXvalue']=$timestamp;

	$block_estimate=linearRegression($regrassionArray_height, $values_height, $count);
	if ($block_estimate>=0) {$block_color="success";}else{$block_color="danger";}
	$mint_estimate=linearRegression($regrassionArray_mint, $values_mint, $count);
	if ($mint_estimate>=0) {$mint_color="success";}else{$mint_color="danger";}
	$annual_inflation=bcmul(bcdiv(bcmul($mint_estimate,365,8),$total_coins,8),100,8);
	$size_estimate=linearRegression($regrassionArray_size, $values_size, $count);
	if ($size_estimate>=0) {$size_color="success";}else{$size_color="danger";}
	$tx_estimate=linearRegression($regrassionArray_tx, $values_tx, $count);
	if ($tx_estimate>=0) {$tx_color="success";}else{$tx_color="danger";}
	$total_addresses_used_estimate=linearRegression($regrassionArray_total_addresses_used, $values_total_addresses_used, $count);
	if ($total_addresses_used_estimate>=0) {$total_addresses_used_color="success";}else{$total_addresses_used_color="danger";}
	$total_addresses_unused_estimate=linearRegression($regrassionArray_total_addresses_unused, $values_total_addresses_unused, $count);
	if ($total_addresses_unused_estimate>=0) {$total_addresses_unused_color="success";}else{$total_addresses_unused_color="danger";}
	$total_addresses_estimate=linearRegression($regrassionArray_total_addresses, $values_total_addresses, $count);
	if ($total_addresses_estimate>=0) {$total_addresses_color="success";}else{$total_addresses_color="danger";}
	$total_avgcoindays_estimate=linearRegression($regrassionArray_total_avgcoindays, $values_total_avgcoindays, $count);
	if ($total_avgcoindays_estimate>=0) {$total_avgcoindays_color="success";}else{$total_avgcoindays_color="danger";}


	$query="SELECT ( YEAR( FROM_UNIXTIME( `time` ) ) *3650 + MONTH( FROM_UNIXTIME( `time` ) ) *120 + DAY( FROM_UNIXTIME( `time` ) ) ) AS `day` , FROM_UNIXTIME( time ) AS time, MAX( id ) AS id, COUNT( id ) AS blocks, AVG(difficulty) AS pow_difficulty
		FROM `blocks`
		WHERE time >= '$date_14days' AND flags = 'proof-of-work'
		GROUP BY ( YEAR( FROM_UNIXTIME( `time` ) ) *3650 + MONTH( FROM_UNIXTIME( `time` ) ) *120 + DAY( FROM_UNIXTIME( `time` ) ) )
		ORDER BY time";
	$result = $dbconn->query($query);
	$count=0;
	$regrassionArray_pow_hashrate=array();
	$values_pow_hashrate=array();
	while($row = $result->fetch_assoc())
	{
		if ($count==0) {
			$values_pow_hashrate['firstXvalue']=$row['id'];
		}
		$count++;

		$pow_difficulty=$row['pow_difficulty'];
		$pow_blocks=$row['blocks'];
		$block_interval=bcdiv(86400,$pow_blocks,8);
		$pow_hashrate=bcdiv(bcmul($pow_difficulty,bcpow(2,32,8),8),$block_interval,8);
		$pow_hashrate=bcdiv($pow_hashrate,1000000000000,8); //to THash
		$regrassionArray_pow_hashrate[$count]['x']=$count;
		$regrassionArray_pow_hashrate[$count]['y']=$pow_hashrate;
		$timestamp=$row['id'];
	}
	$values_pow_hashrate['lastXvalue']=$timestamp;
	$hashrate_estimate=linearRegression($regrassionArray_pow_hashrate, $values_pow_hashrate, $count);
	if ($hashrate_estimate>=0) {$hashrate_color="success";}else{$hashrate_color="danger";}

	$query="SELECT (
		YEAR( FROM_UNIXTIME( `time` ) ) *3650 + MONTH( FROM_UNIXTIME( `time` ) ) *120 + DAY( FROM_UNIXTIME( `time` ) )
		) AS `day` , FROM_UNIXTIME( time ) AS time, AVG( difficulty ) AS pow_difficulty, COUNT(id) AS pow_blocks, MAX( id ) AS id
		FROM `blocks`
		WHERE time >= '$date_14days' AND flags LIKE '%proof-of-work%'
		GROUP BY (
		YEAR( FROM_UNIXTIME( `time` ) ) *3650 + MONTH( FROM_UNIXTIME( `time` ) ) *120 + DAY( FROM_UNIXTIME( `time` ) )
		)
		ORDER BY time";
	$result = $dbconn->query($query);
	$count=0;
	$regrassionArray_pow_blocks=array();
	$values_pow_blocks=array();
	$regrassionArray_pow_difficulty=array();
	$values_pow_difficulty=array();
	$pow_blocks_sum=0;
	while($row = $result->fetch_assoc())
	{
		if ($count==0) {
			$values_pow_blocks['firstXvalue']=$row['id'];
			$values_pow_difficulty['firstXvalue']=$row['id'];
		}
		$count++;
		$regrassionArray_pow_blocks[$count]['x']=$count;
		$pow_blocks_sum=bcadd($pow_blocks_sum,$row['pow_blocks']);
		$regrassionArray_pow_blocks[$count]['y']=$pow_blocks_sum;
		$regrassionArray_pow_difficulty[$count]['x']=$count;
		$regrassionArray_pow_difficulty[$count]['y']=$row['pow_difficulty'];
		$timestamp=$row['id'];
	}
	$values_pow_blocks['lastXvalue']=$timestamp;
	$values_pow_difficulty['lastXvalue']=$timestamp;
	$pow_estimate=linearRegression($regrassionArray_pow_difficulty, $values_pow_difficulty, $count);
	if ($pow_estimate>=0) {$pow_color="success";}else{$pow_color="danger";}
	$pow_blocks_estimate=linearRegression($regrassionArray_pow_blocks, $values_pow_blocks, $count);
	if ($pow_blocks_estimate>=0) {$pow_blocks_color="success";}else{$pow_blocks_color="danger";}

	$query="SELECT (
		YEAR( FROM_UNIXTIME( `time` ) ) *3650 + MONTH( FROM_UNIXTIME( `time` ) ) *120 + DAY( FROM_UNIXTIME( `time` ) )
		) AS `day` , FROM_UNIXTIME( time ) AS time, AVG( difficulty ) AS pos_difficulty, COUNT(id) AS pos_blocks, MAX( id ) AS id
		FROM `blocks`
		WHERE time >= '$date_14days' AND flags LIKE '%proof-of-stake%'
		GROUP BY (
		YEAR( FROM_UNIXTIME( `time` ) ) *3650 + MONTH( FROM_UNIXTIME( `time` ) ) *120 + DAY( FROM_UNIXTIME( `time` ) )
		)
		ORDER BY time";
	$result = $dbconn->query($query);
	$count=0;
	$regrassionArray_pos_blocks=array();
	$values_pos_blocks=array();
	$regrassionArray_pos_difficulty=array();
	$values_pos_difficulty=array();
	$pos_blocks_sum=0;
	while($row = $result->fetch_assoc())
	{
		if ($count==0) {
			$values_pos_blocks['firstXvalue']=$row['id'];
			$values_pos_difficulty['firstXvalue']=$row['id'];
		}
		$count++;
		$regrassionArray_pos_blocks[$count]['x']=$count;
		$pos_blocks_sum=bcadd($pos_blocks_sum,$row['pos_blocks']);
		$regrassionArray_pos_blocks[$count]['y']=$pos_blocks_sum;
		$regrassionArray_pos_difficulty[$count]['x']=$count;
		$regrassionArray_pos_difficulty[$count]['y']=$row['pos_difficulty'];
		$timestamp=$row['id'];
	}
	$values_pos_blocks['lastXvalue']=$timestamp;
	$values_pos_difficulty['lastXvalue']=$timestamp;
	$pos_estimate=linearRegression($regrassionArray_pos_difficulty, $values_pos_difficulty, $count);
	if ($pos_estimate>=0) {$pos_color="success";}else{$pos_color="danger";}
	$pos_blocks_estimate=linearRegression($regrassionArray_pos_blocks, $values_pos_blocks, $count);
	if ($pos_blocks_estimate>=0) {$pos_blocks_color="success";}else{$pos_blocks_color="danger";}

	?>
	<div class="panel panel-default">
		<div class="panel-heading"><a class="btn btn-primary" data-toggle="collapse" href="#BlockChainStatistics" aria-expanded="true" aria-controls="BlockChainStatistics"><?php echo lang('BLOCKCHAIN_STATISTICS'); ?></a></div>
		<div class="panel-body collapse" id="BlockChainStatistics">
		<script>
			$('#BlockChainStatistics').collapse('show')
		</script>
			<table class="table">
			<?php
				echo '<tr><td width="25%">'.lang('CHAIN_LENGTH').'</td><td width="15%">'.$height.' '.lang('BLOCKS_BLOCKS').'</td><td><span class="label label-'.$block_color.'">'.TrimTrailingZeroes(number_format($block_estimate,2)).'</span> <span class="label label-'.$pos_blocks_color.'">PoS: '.TrimTrailingZeroes(number_format($pos_blocks_estimate,2)).'</span> <span class="label label-'.$pow_blocks_color.'">PoW: '.TrimTrailingZeroes(number_format($pow_blocks_estimate,2)).'</span></td></tr>';
				echo '<tr><td>'.lang('COINS_AVAILABLE').'</td><td>'.TrimTrailingZeroes(number_format($total_coins,2)).' EMC</td><td><span class="label label-'.$mint_color.'">'.TrimTrailingZeroes(number_format($mint_estimate,2)).' EMC</span> <span class="label label-'.$mint_color.'">'.lang('ANNUAL_GROWTH').': '.TrimTrailingZeroes(number_format($annual_inflation,2)).' %</span></td></tr>';
				echo '<tr><td>'.lang('POW_DIFFICULTY').'</td><td>'.TrimTrailingZeroes(number_format($current_pow_difficulty,2)).'</td><td><span class="label label-'.$pow_color.'">'.TrimTrailingZeroes(number_format($pow_estimate,2)).'</span></td></tr>';
				echo '<tr><td>PoW Hashrate</td><td>'.TrimTrailingZeroes(number_format($current_pow_hashrate,2)).' TH/s</td><td><span class="label label-'.$hashrate_color.'">'.TrimTrailingZeroes(number_format($hashrate_estimate,2)).' TH/s</span></td></tr>';
				echo '<tr><td>'.lang('POS_DIFFICULTY').'</td><td >'.TrimTrailingZeroes(number_format($current_pos_difficulty,2)).'</td><td><span class="label label-'.$pos_color.'">'.TrimTrailingZeroes(number_format($pos_estimate,2)).'</span></td></tr>';
				echo '<tr><td>'.lang('TRANSACTIONS_TRANSACTIONS').'</td><td >'.TrimTrailingZeroes(number_format($alltransactions,2)).'</td><td><span class="label label-'.$tx_color.'">'.TrimTrailingZeroes(number_format($tx_estimate,2)).'</span></td></tr>';
				echo '<tr><td>'.lang('AVG_COIN').'</td><td >'.TrimTrailingZeroes(number_format($total_avgcoindays,2)).'</td><td><span class="label label-'.$total_avgcoindays_color.'">'.TrimTrailingZeroes(number_format($total_avgcoindays_estimate,2)).'</span></td></tr>';
				echo '<tr><td>'.lang('CHAIN_SIZE').'</td><td>'.TrimTrailingZeroes(number_format($size,2)).' '.$unit.'</td><td><span class="label label-'.$size_color.'">'.$size_24.' '.$unit_24.'</span></td></tr>';
				echo '<tr><td>'.lang('KNOWN_ADDRESSES').'</td><td colspan="2">
				<table class="table">
				<tr><th>'.lang('USED_USED').'</th><th>'.lang('UNUSED_UNUSED').'</th><th>'.lang('TOTAL_TOTAL').'</th></tr>
				<tr><td>'.number_format($total_addresses_used,0).' <span class="label label-'.$total_addresses_used_color.'">'.TrimTrailingZeroes(number_format($total_addresses_used_estimate,2)).'</span></td><td>'.number_format($total_addresses_unused,0).' <span class="label label-'.$total_addresses_unused_color.'">'.TrimTrailingZeroes(number_format($total_addresses_unused_estimate,2)).'</span></td><td>'.number_format(($total_addresses_used+$total_addresses_unused),0).' <span class="label label-'.$total_addresses_color.'">'.TrimTrailingZeroes(number_format($total_addresses_estimate,2)).'</span></td></tr>
				</table>
				</td></tr>';
			?>
			</table>
		</div>
		<div class="panel-footer"><footer class="text-muted"><i><sub><?php echo lang('GENERAL_DAYS'); ?></sub></i></footer></div>
	</div>

	<div class="panel panel-default">
		<div class="panel-heading"><a class="btn btn-primary" data-toggle="collapse" href="#24hStatistics" aria-expanded="false" aria-controls="24hStatistics"><?php echo lang('24H_STATISTICS'); ?></a></div>
		<div class="panel-body collapse" id="24hStatistics">
			<table class="table">
			<?php
				echo '<tr><td>'.lang('BLOCKS_FOUND').'</td><td width="75%">'.$blocks.' (PoS:'.$pos.' / PoW:'.($blocks-$pos).')</td></tr>';
				echo '<tr><td>'.lang('EMC_MINTED').'</td><td>'.TrimTrailingZeroes(number_format($minted,8)).' EMC</td></tr>';
				echo '<tr><td>'.lang('MINUTES_BLOCKS').'</td><td>'.TrimTrailingZeroes(number_format((1440/$blocks),2)).'</td></tr>';
				echo '<tr><td>'.lang('TRANSACTIONS_TRANSACTIONS').'</td><td>'.$transactions.'</td></tr>';
				echo '<tr><td>'.lang('TOTAL_FEES').'</td><td>'.TrimTrailingZeroes(number_format($fees,8)).' EMC</td></tr>';
				echo '<tr><td>'.lang('TOTAL_OUTPUT').'</td><td>'.TrimTrailingZeroes(number_format($tx_out,8)).' EMC</td></tr>';
				echo '<tr><td>'.lang('COIN_DESTROYED').'</td><td>'.TrimTrailingZeroes(number_format($coin_dest,8)).' => '.TrimTrailingZeroes(number_format(($coin_dest/$tx_in),8)).' '.lang('DAYS_COIN').'</td></tr>';
			?>
			</table>
		</div>
		<div class="panel-footer"><footer class="text-muted"><i><sub><?php echo lang('BASED_H'); ?></sub></i></footer></div>
	</div>
	<div class="panel panel-default">
		<div class="panel-heading"><a class="btn btn-primary" data-toggle="collapse" href="#24hTXStatistics" aria-expanded="false" aria-controls="24hTXStatistics"><?php echo lang('24H_STATISTICS'); ?></a></div>
		<div class="panel-body collapse" id="24hTXStatistics">
			<table class="table">
			<?php
				echo '<tr><td>'.lang('TRANSACTIONS_TRANSACTIONS').'</td><td width="75%">'.$wo_totaltx.'</td></tr>';
				echo '<tr><td>'.lang('TOTAL_FEES').'</td><td>'.TrimTrailingZeroes(number_format($wo_fees,7)).' EMC</td></tr>';
				echo '<tr><td>'.lang('TOTAL_OUTPUT').'</td><td>'.TrimTrailingZeroes(number_format($wo_tx_out,8)).' EMC</td></tr>';
				echo '<tr><td>'.lang('COIN_DESTROYED').'</td><td>'.TrimTrailingZeroes(number_format($wo_coin_dest,8)).' => '.TrimTrailingZeroes(number_format($wo_avg_coin_dest,8)).' '.lang('DAYS_COIN').'</td></tr>';
			?>
			</table>
		</div>
		<div class="panel-footer"><footer class="text-muted"><i><sub><?php echo lang('BASED_TRANSACTIONS'); ?></sub></i></footer></div>
	</div>
	<?php
	$query="SELECT MAX(height) AS height FROM blocks";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$height=$row['height'];
	}
	$query="SELECT COUNT(id) AS total_values FROM nvs";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$total_values=$row['total_values'];
	}
	$query="SELECT COUNT(id) AS total_values FROM nvs WHERE expires_at > '$height'";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$total_valid_values=$row['total_values'];
	}
	$query="SELECT COUNT(type) AS total_dns FROM nvs WHERE type = 'dns' AND expires_at > '$height'";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$total_dns=$row['total_dns'];
	}
	$query="SELECT type, COUNT(type) AS count
	FROM `nvs`
	WHERE expires_at > '$height'
	GROUP BY type
	ORDER by count DESC
	LIMIT 5
	";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		if ($row['type']=="") {$row['type']="N/A";}
		$types[$row['type']]=$row['count'];
	}
	if (isset($types)) {
		arsort($types);
	}
	?>
	<div class="panel panel-default">
		<div class="panel-heading"><a class="btn btn-primary" data-toggle="collapse" href="#24hNVSStatistics" aria-expanded="false" aria-controls="24hNVSStatistics"><?php echo lang('NAME_STATISITICS'); ?></a></div>
		<div class="panel-body collapse" id="24hNVSStatistics">
			<table class="table">
			<?php
				echo '<tr><td>'.lang('TOTAL_VALUES').'</td><td width="75%">'.$total_values.'</td></tr>';
				echo '<tr><td>'.lang('VALID_VALUES').'</td><td>'.$total_valid_values.'</td></tr>';
				echo '<tr><td>'.lang('VALID_RECORDS').'</td><td>'.$total_dns.'</td></tr>';
				echo '<tr><td>'.lang('TOP_TYPES').'</td><td>';
				foreach ($types as $type => $value) {
					echo $type.': <span class="badge">'.$value.'</span><br>';
				}
				echo'</td></tr>';
			?>
			</table>
		</div>
		<div class="panel-footer"></div>
	</div>


</div>
<?php
function linearRegression ($regrassionArray, $values, $count) {
	$x_avg=0;
	$y_avg=0;
	for ($i=1; $i<=$count; $i++) {
		$x_avg=bcadd($x_avg,$regrassionArray[$i]['x'],8);
		$y_avg=bcadd($y_avg,$regrassionArray[$i]['y'],8);
	}
	if ($count != 0) {
		$x_avg=bcdiv($x_avg,$count,8);
	} else {
		$x_avg=0;
	}
	if ($count != 0) {
		$y_avg=bcdiv($y_avg,$count,8);
	} else {
		$y_avg=0;
	}
	$x_avg_diff_sum=0;
	$y_avg_diff_sum=0;
	$x_avg_diff_X_y_avg_diff_sum=0;
	$x_avg_X2_sum=0;
	$y_avg_X2_sum=0;
	for ($i=1; $i<=$count; $i++) {
		$regrassionArray[$i]['x_avg_diff']=bcsub($regrassionArray[$i]['x'],$x_avg,8);
		$x_avg_diff_sum=bcadd($x_avg_diff_sum,$regrassionArray[$i]['x_avg_diff'],8);
		$regrassionArray[$i]['y_avg_diff']=bcsub($regrassionArray[$i]['y'],$y_avg,8);
		$y_avg_diff_sum=bcadd($y_avg_diff_sum,$regrassionArray[$i]['y_avg_diff'],8);
		$regrassionArray[$i]['x_avg_diff_X_y_avg_diff']=bcmul($regrassionArray[$i]['x_avg_diff'],$regrassionArray[$i]['y_avg_diff'],8);
		$x_avg_diff_X_y_avg_diff_sum=bcadd($x_avg_diff_X_y_avg_diff_sum,$regrassionArray[$i]['x_avg_diff_X_y_avg_diff'],8);
		$regrassionArray[$i]['x_avg_X2']=bcmul($regrassionArray[$i]['x_avg_diff'],$regrassionArray[$i]['x_avg_diff'],8);
		$x_avg_X2_sum=bcadd($x_avg_X2_sum,$regrassionArray[$i]['x_avg_X2'],8);
		$regrassionArray[$i]['y_avg_X2']=bcmul($regrassionArray[$i]['y_avg_diff'],$regrassionArray[$i]['y_avg_diff'],8);
		$y_avg_X2_sum=bcadd($y_avg_X2_sum,$regrassionArray[$i]['y_avg_X2'],8);
	}
	if ($count != 0) {
		$x_avg_diff_X_y_avg_diff_sum_avg=bcdiv($x_avg_diff_X_y_avg_diff_sum,$count,8);
	} else {
		$x_avg_diff_X_y_avg_diff_sum_avg=0;
	}
	if ($count != 0) {
		$x_avg_X2_sum_avg=bcdiv($x_avg_X2_sum,$count,8);
	} else {
		$x_avg_X2_sum_avg=0;
	}
	if ($count != 0) {
		$y_avg_X2_sum_avg=bcdiv($y_avg_X2_sum,$count,8);
	} else {
		$y_avg_X2_sum_avg=0;
	}

	$Sx=sqrt($x_avg_X2_sum_avg);
	$Sy=sqrt($y_avg_X2_sum_avg);
	if (($Sy*$Sx)!=0) {
		$Ryx=bcdiv($x_avg_diff_X_y_avg_diff_sum_avg,bcmul($Sy,$Sx,8),8);
		$Myx=bcmul($Ryx,bcdiv($Sy,$Sx,8),8);
	}
	else {
		$Myx=0;
	}

	return $Myx;
}
?>
