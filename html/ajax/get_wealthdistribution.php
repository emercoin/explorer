		<?php
	
		if (!empty($_COOKIE["lang"])) {
			$lang=$_COOKIE["lang"];
			require("../lang/".$lang.".php");
		} else {
			setcookie("lang","en",time()+(3600*24*14), "/");
			require("../lang/en.php");
		}

	?>
	
	<h3><?php echo lang('WEALTH_DISTRIBUTION'); ?></h3>
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
	
	$block_total_coins=$_GET['total_coins'];
	
	$query="SELECT SUM( balance ) AS balance
		FROM address
		GROUP BY address
		ORDER BY SUM( balance ) DESC
		LIMIT 1000";
	$result = $dbconn->query($query);
	$count=1;
	$top10=0;
	$top100=0;
	$top1000=0;
	while($row = $result->fetch_assoc())
	{	
		if ($count<=10) {	
			$top10=($top10+$row['balance']);
		}
		if ($count>10 && $count<=100) {	
			$top100=($top100+$row['balance']);
		}
		if ($count>100 && $count<=1000) {	
			$top1000=($top1000+$row['balance']);
		}
		$count++;
	}
	?>
			<script type="text/javascript">
$(function () {
	Highcharts.getOptions().plotOptions.pie.colors = (function () {
        var colors = [],
            base = '#A901DB',
            i;

        for (i = 0; i < 10; i += 1) {
            // Start out with a darkened base color (negative brighten), and end
            // up with a much brighter color
            colors.push(Highcharts.Color(base).brighten((i - 3) / 7).get());
        }
        return colors;
    }());

    $('#addresschart').highcharts({
        chart: {
            plotBackgroundColor: null,
            plotBorderWidth: null,
            plotShadow: false
        },
        title: {
            text: ''
        },
        tooltip: {
            pointFormat: '{series.name}: <b>{point.percentage:.1f}%</b>'
        },
        plotOptions: {
            pie: {
                allowPointSelect: true,
                cursor: 'pointer',
                dataLabels: {
                    enabled: true,
                    format: '<b>{point.name}</b>: {point.percentage:.1f} %',
                    style: {
                        color: (Highcharts.theme && Highcharts.theme.contrastTextColor) || 'black'
                    }
                }
            }
        },
        series: [{
            type: 'pie',
            name: '<?php echo lang('COIN_DISTRIBUTION'); ?>',
            data: [
                ['Top10',<?php echo $top10; ?>],
				['Top11-100',<?php echo $top100; ?>],
				['Top101-1000',<?php echo $top1000; ?>],
				['<?php echo lang('OTHERS_OTHERS'); ?>',<?php echo ($block_total_coins-($top1000+$top100+$top10)); ?>]
            ]
        }],
		credits: {
				enabled: false
			},
    });
});


		</script>
		<div class="row">
			<div class="col-md-5">
			<table class="table table-striped">
				<tr>
					<th><?php echo lang('TOP_ADDRESSES'); ?></th><th><?php echo lang('HOLDINGS_EMC'); ?></th><th><?php echo lang('PERCENTAGE_PERCENTAGE'); ?></th>
				</tr>
				<tr>
					<td>Top 10</td><td><?php echo TrimTrailingZeroes(number_format(round($top10,7),8)); ?></td><td><?php echo round(($top10*100/$block_total_coins),2); ?></td>
				</tr>
				<tr>
					<td>Top 100</td><td><?php echo TrimTrailingZeroes(number_format(round($top10+$top100,7),8)); ?></td><td><?php echo round((($top10+$top100)*100/$block_total_coins),2); ?></td>
				</tr>
				<tr>
					<td>Top 1000</td><td><?php echo TrimTrailingZeroes(number_format(round($top10+$top100+$top1000,7),8)); ?></td><td><?php echo round((($top10+$top100+$top1000)*100/$block_total_coins),2); ?></td>
				</tr>
				<tr>
					<td><?php echo lang('ALL_ALL'); ?></td><td><?php echo TrimTrailingZeroes(number_format(round($block_total_coins,7),8)); ?></td><td><?php echo "100"; ?></td>
				</tr>
			</table>
			</div>
			<div id="addresschart" class="col-md-7" style="min-width: 310px; height: 400px; max-width: 600px; margin: 0 auto"></div>
		</div>
		