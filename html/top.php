<?php
function TrimTrailingZeroes($nbr) {
    return strpos($nbr,'.')!==false ? rtrim(rtrim($nbr,'0'),'.') : $nbr;
}

$query = "SELECT total_coins FROM blocks ORDER BY height DESC LIMIT 1";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$block_total_coins=$row['total_coins'];
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
?>



<div class="container">
	<ul class="nav nav-tabs">
		<li role="top100" id="tab_address" class="active"><a href="javascript:showAddresses();"><?php echo lang('TOP_ADDRESSES'); ?></a></li>
		<li role="top100" id="tab_account" ><a href="javascript:showAccounts();"><?php echo lang('TOP_ACCOUNTS'); ?></a></li>
		<li role="top100" id="tab_charts" ><a href="javascript:showCharts();"><?php echo lang('WEALTH_DISTRIBUTION'); ?></a></li>
	</ul>
	
<div id="address_div">	
	<i class="fa fa-spinner fa-3x fa-pulse"></i>
</div>	

<div id="account_div">
	<i class="fa fa-spinner fa-3x fa-pulse"></i>
</div>

<div id="chart_div">
	<i class="fa fa-spinner fa-3x fa-pulse"></i>
</div>

</div>
<script>
function get_topaddresses() {
	$.ajax({
	url: "/ajax/get_topaddresses.php?total_coins=<?php echo $block_total_coins; ?>",
	cache: false
	})
	.done(function( html ) {
		$('#address_div').html(html);
	});
}

function get_topaccounts() {
	$.ajax({
	url: "/ajax/get_topaccounts.php?total_coins=<?php echo $block_total_coins; ?>",
	cache: false
	})
	.done(function( html ) {
		$('#account_div').html(html);
	});
}

function get_wealthdistribution() {
	$.ajax({
	url: "/ajax/get_wealthdistribution.php?total_coins=<?php echo $block_total_coins; ?>",
	cache: false
	})
	.done(function( html ) {
		$('#chart_div').html(html);
	});
}

$( document ).ready(function() {
	$('#account_div').hide();
	$('#chart_div').hide();
	get_topaddresses();
	get_topaccounts();
	get_wealthdistribution();	
});

function showAddresses() {
	if ( !$('#tab_address').hasClass('active') ) {
		$('#tab_address').addClass('active');
		$('#tab_account').removeClass('active');
		$('#tab_charts').removeClass('active');
		$('#address_div').show();
		$('#account_div').hide();
		$('#chart_div').hide();	
	}
}

function showAccounts() {
	if ( !$('#tab_account').hasClass('active') ) {
		$('#tab_account').addClass('active');
		$('#tab_address').removeClass('active');
		$('#tab_charts').removeClass('active');
		$('#address_div').hide();
		$('#account_div').show();
		$('#chart_div').hide();
	}
}

function showCharts() {
	if ( !$('#tab_charts').hasClass('active') ) {
		$('#tab_charts').addClass('active');
		$('#tab_address').removeClass('active');
		$('#tab_account').removeClass('active');
		$('#address_div').hide();
		$('#account_div').hide();
		$('#chart_div').show();
	}
}
</script>