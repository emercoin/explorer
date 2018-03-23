<?php
//check current chain height

$query = "SELECT height FROM blocks ORDER BY height DESC LIMIT 1";
$result = $dbconn->query($query);
while($row = $result->fetch_assoc())
{
	$block_current_height=$row['height'];
}
$diff=0;
if (isset($_SERVER['REQUEST_URI'])) {
	$URI=explode('/',$_SERVER['REQUEST_URI']);
	if ($URI[1]=="chain") {
		if (isset($URI[2])) {
			$blockSelection=explode('-',$URI[2]);
			if (isset($blockSelection[0]) && isset($blockSelection[1])) {
				$block_height_reduced=0;
				if (is_numeric($blockSelection[0])) {
					$block_from_height=$blockSelection[0];
					if ($block_from_height>$block_current_height) {
						$block_from_height=$block_current_height;
						$block_height_reduced=1;
					}
				}
				if (is_numeric($blockSelection[1])) {
					$block_to_height=$blockSelection[1];
					if ($block_height_reduced==1) {
						$block_to_height=$block_from_height-14;
					}
				}
				$diff = $block_from_height - $block_to_height;
				if ($diff<0) {
					$diff=$diff*(-1);
				}
				if ($diff>1000) {
					if ($block_from_height>$block_to_height) {
						$block_to_height=($block_from_height-1000);
					} else {
						$block_to_height=($block_from_height+1000);
					}
				}
			}
		}
	}
}


if (!isset($block_from_height) || !isset($block_to_height)) {
	$block_from_height=$block_current_height;
	$block_to_height=$block_from_height-14;
	if ($block_to_height<=0) {
		$block_to_height=0;
	}
}

if ($block_from_height>$block_to_height) {
	$order_block_by="DESC";
	$showBlocksQuery = "SELECT * FROM blocks WHERE height >= '$block_to_height' AND height <= '$block_from_height' ORDER BY height $order_block_by";
	$showPoSQuery="SELECT blocks.height, tx.coindaysdestroyed AS coindaysdestroyed, (
			 tx.coindaysdestroyed / tx.valuein
			 ) AS avgcoindaysdestroyed, tx.valuein
			 FROM transactions AS tx
			 INNER JOIN blocks ON blocks.id = tx.blockid
			 AND blocks.height >= '$block_to_height'
			 AND blocks.height <= '$block_from_height'
			 WHERE tx.fee < '0'
			 ORDER BY blocks.height $order_block_by";
} else {
	$order_block_by="ASC";
	$showBlocksQuery = "SELECT * FROM blocks WHERE height <= '$block_to_height' AND height >= '$block_from_height' ORDER BY height $order_block_by";
	$showPoSQuery="SELECT blocks.height, tx.coindaysdestroyed AS coindaysdestroyed, (
			 tx.coindaysdestroyed / tx.valuein
			 ) AS avgcoindaysdestroyed, tx.valuein
			 FROM transactions AS tx
			 INNER JOIN blocks ON blocks.id = tx.blockid
			 AND blocks.height <= '$block_to_height'
			 AND blocks.height >= '$block_from_height'
			 WHERE tx.fee < '0'
			 ORDER BY blocks.height $order_block_by";
}

function TrimTrailingZeroes($nbr) {
    return strpos($nbr,'.')!==false ? rtrim(rtrim($nbr,'0'),'.') : $nbr;
}

function muteCents($balance) {
	return substr($balance, 0, (strpos($balance, ".")+3)) . '<small class="text-muted">' . substr($balance, (strpos($balance, ".")+3)).'</small>';
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
?>


<div class="container">

<div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
  <div class="panel panel-default">
    <div class="panel-heading" role="tab" id="headingOne">
      <h4 class="panel-title">
        <a data-toggle="collapse" data-parent="#accordion" href="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
          <?php echo lang('SELECT_RANGE'); ?>
        </a>
      </h4>
    </div>
    <div id="collapseOne" class="panel-collapse collapse in" role="tabpanel" aria-labelledby="headingOne">
      <div class="panel-body">
     <form class="form-inline">
	  <div class="form-group">
		<label for="inputFromBlock"></label>
		<input type="text" class="form-control" id="inputFromBlock" placeholder="Block ID" value="<?php echo $block_from_height; ?>">
	  </div>
	  <div class="form-group">
		<label for="inputToBlock">-</label>
		<input type="text" class="form-control" id="inputToBlock" placeholder="Block ID" value="<?php echo $block_to_height; ?>">
	  </div>
	  <a class="btn btn-primary" onclick="javascript:sendChainValues();" role="button"><?php echo lang('SHOW_SHOW'); ?></a>
	</form>
	</div>
    </div>
  </div>
</div>

<script type="text/javascript" language="javascript" class="init">
$(document).ready(function() {
   var table=$('#chain').DataTable( {
	    "oLanguage": {
		  "sSearch": "<?php echo lang('SEARCH_SEARCH'); ?>:"
		},
		"stateSave": false,
        "paging":   false,
        "ordering": true,
        "info":     false,
		 "aoColumnDefs": [
      { "bVisible": false, "aTargets": [ 3,5,6,7,8,10,11,15,16 ] }
    ],
		"dom": 'C<"clear">lfrtip',
		"aaSorting": []
    });
	$('.ColVis_Button').html("<?php echo lang('SHOW_COLUMNS'); ?>");
} );
</script>

	<script>
	function sendChainValues() {
		var from = document.getElementById('inputFromBlock').value;
		from = parseInt(from,10);
		var to = document.getElementById('inputToBlock').value;
		to = parseInt(to,10);
		var diff = from - to;
		if (diff<0) {
			diff=diff*(-1);
		}
		if (diff>1000) {
			if (from>to) {
				to=(from-1000);
			} else {
				to=(from+1000);
			}
		}
		window.location.href = '/chain/'+from+'-'+to;
	};
	</script>

<p>
	<?php
	if ($diff>=100) {
	echo '<nav>
		<ul class="pager">
			<li class="previous"><a href="javascript:sendPrevChainValues();"><span aria-hidden="true"><i class="fa fa-arrow-circle-left"></i></span> '.lang('OLDER_OLDER').'</a></li>
			<li class="next"><a href="javascript:sendNextChainValues();">'.lang('NEWER_NEWER').' <span aria-hidden="true"><i class="fa fa-arrow-circle-right"></i></span></a></li>
		</ul>
	</nav>';
	}
	?>

	<table id="chain" class="table table-striped" cellspacing="0" width="100%">
	<thead>
		<tr>
			<th><?php echo lang('ID_ID'); ?></th>
			<th><?php echo lang('FLAG_FLAG'); ?></th>
			<th><?php echo lang('POS_EMC'); ?></th>
			<th><?php echo lang('POS_DAYS'); ?></th>
			<th><?php echo lang('POS_COIN'); ?></th>
			<th><?php echo lang('DIFFICULTY_DIFFICULTY'); ?></th>
			<th><?php echo lang('COIN_EMC'); ?></th>
			<th><?php echo lang('CHAIN_DAYS'); ?></th>
			<th><?php echo lang('AVGCHAIN_DAYS'); ?></th>
			<th><?php echo lang('REWARD_EMC'); ?></th>
			<th><?php echo lang('IN_ADDRESSES'); ?></th>
			<th><?php echo lang('EMPTY_ADDRESSES'); ?></th>
			<th><?php echo lang('TIME_AGO'); ?></th>
			<th><?php echo lang('TX_COUNT'); ?></th>
			<th><?php echo lang('TX_VOLUME_EMC'); ?></th>
			<th><?php echo lang('TX_DAYS'); ?></th>
			<th><?php echo lang('TX_COIN'); ?></th>
			<th><?php echo lang('TX_FEE_EMC'); ?></th>
		</tr>
	</thead>
	<tfoot>
		<tr>
			<th><?php echo lang('ID_ID'); ?></th>
			<th><?php echo lang('FLAG_FLAG'); ?></th>
			<th><?php echo lang('POS_EMC'); ?></th>
			<th><?php echo lang('POS_DAYS'); ?></th>
			<th><?php echo lang('POS_COIN'); ?></th>
			<th><?php echo lang('DIFFICULTY_DIFFICULTY'); ?></th>
			<th><?php echo lang('COIN_EMC'); ?></th>
			<th><?php echo lang('CHAIN_DAYS'); ?></th>
			<th><?php echo lang('AVGCHAIN_DAYS'); ?></th>
			<th><?php echo lang('REWARD_EMC'); ?></th>
			<th><?php echo lang('IN_ADDRESSES'); ?></th>
			<th><?php echo lang('EMPTY_ADDRESSES'); ?></th>
			<th><?php echo lang('TIME_AGO'); ?></th>
			<th><?php echo lang('TX_COUNT'); ?></th>
			<th><?php echo lang('TX_VOLUME_EMC'); ?></th>
			<th><?php echo lang('TX_DAYS'); ?></th>
			<th><?php echo lang('TX_COIN'); ?></th>
			<th><?php echo lang('TX_FEE_EMC'); ?></th>
		</tr>
	</tfoot>
	<tbody>
	<?php
	$showPoSArray=array();
	$result = $dbconn->query($showPoSQuery);
	while($row = $result->fetch_assoc())
	{
		$showPoSArray[$row['height']]['input']=$row['valuein'];
		$showPoSArray[$row['height']]['coindays']=$row['coindaysdestroyed'];
		$showPoSArray[$row['height']]['avgcoindays']=$row['avgcoindaysdestroyed'];
	}


	$result = $dbconn->query($showBlocksQuery);
	while($row = $result->fetch_assoc())
	{
		$block_height=$row['height'];
		$block_hash=$row['hash'];
		$block_total_coins=$row['total_coins'];
		$block_mint=$row['mint'];
		$block_numtx=$row['numtx'];
		$block_valueout=$row['valueout'];
		$block_flag=$row['flags'];
		$block_fee=$row['fee'];
		$block_time=$row['time'];
		$total_addresses_used=$row['total_addresses_used'];
		$total_addresses_unused=$row['total_addresses_unused'];
		$block_difficulty=$row['difficulty'];
		$total_avgcoindays=$row['total_avgcoindays'];
		$total_coindays=$row['total_coindays'];
		$avgcoindays=$row['avgcoindaysdestroyed'];
		$coindays=$row['coindaysdestroyed'];
		$stake_input="";
		$stake_coindays="";
		$stake_avgcoindays="";
		if (isset($showPoSArray[$block_height]['input'])) {
			$stake_input=$showPoSArray[$block_height]['input'];
			$stake_input=muteCents(TrimTrailingZeroes(number_format($stake_input,8)));
			$stake_coindays=$showPoSArray[$block_height]['coindays'];
			$stake_coindays=TrimTrailingZeroes(number_format($stake_coindays,2));
			$stake_avgcoindays=$showPoSArray[$block_height]['avgcoindays'];
			$stake_avgcoindays=TrimTrailingZeroes(number_format($stake_avgcoindays,2));
		}

		$block_read_time=date("Y-m-d G:i:s",$block_time);
		if (strpos($block_flag,'proof-of-work') !== false) {
			$block_flag="PoW";
			$flagcolor="danger";
		} else {
			$block_flag="PoS";
			$flagcolor="success";
		}
		if ($block_flag=="PoS") {
			$block_fee=bcadd($block_fee,$block_mint,8);
		}

		echo '
		<tr>
			<td><a href="/block/'.$block_hash.'" class="btn btn-primary btn-xs" role="button">'.$block_height.'</a></td>
			<td class="'.$flagcolor.'" >'.$block_flag.'</td>
			<td>'.$stake_input.'</td>
			<td>'.$stake_coindays.'</td>
			<td>'.$stake_avgcoindays.'</td>
			<td>'.muteCents(TrimTrailingZeroes(number_format($block_difficulty,8))).'</td>
			<td>'.muteCents(TrimTrailingZeroes(number_format($block_total_coins,6))).'</td>
			<td>'.muteCents(TrimTrailingZeroes(number_format($total_coindays,8))).'</td>
			<td>'.muteCents(TrimTrailingZeroes(number_format($total_avgcoindays,8))).'</td>
			<td>'.muteCents(TrimTrailingZeroes(number_format($block_mint,6))).'</td>
			<td>'.$total_addresses_used.'</td>
			<td>'.$total_addresses_unused.'</td>
			<td><abbr title="'.$block_read_time.'">'.timeAgo($block_time).'</abbr></td>
			<td>'.$block_numtx.'</td>
			<td>'.muteCents(TrimTrailingZeroes(number_format($block_valueout,6))).'</td>
			<td>'.TrimTrailingZeroes(number_format($coindays,2)).'</td>
			<td>'.TrimTrailingZeroes(number_format($avgcoindays,2)).'</td>
			<td>'.TrimTrailingZeroes(number_format($block_fee,6)).'</td>
		</tr>';
	}
	?>
	</tbody>
	</table>

	<nav>
		<ul class="pager">
			<li class="previous"><a href="javascript:sendPrevChainValues();"><span aria-hidden="true"><i class="fa fa-arrow-circle-left"></i></span> <?php echo lang('OLDER_OLDER'); ?></a></li>
			<li class="next"><a href="javascript:sendNextChainValues();"><?php echo lang('NEWER_NEWER'); ?> <span aria-hidden="true"><i class="fa fa-arrow-circle-right"></i></span></a></li>
		</ul>
	</nav>

	<script>
	function sendPrevChainValues() {
		var from = $('#inputFromBlock').val();
		var to = $('#inputToBlock').val();
		var diff = from - to;
		if (diff<0) {
			diff=diff*(-1);
		}
		if (diff<=1000) {
			from=(from-diff);
			to=(to-diff);
			if (from < 0 || to < 0) {
				from=diff;
				to=0;
			}
		} else {
			from=(from-1000);
			to=(to-1000);
		}
		window.location.href = '/chain/'+from+'-'+to;
	};
	</script>

	<script>
	function sendNextChainValues() {
		var from = $('#inputFromBlock').val();
		from = parseInt(from,10);
		var to = $('#inputToBlock').val();
		to = parseInt(to,10);
		var diff = from - to;
		if (diff<0) {
			diff=diff*(-1);
		}
		if (diff<=1000) {
			from=(from+diff);
			to=(to+diff);
		} else {
			from=(from+1000);
			to=(to+1000);
		}
		window.location.href = '/chain/'+from+'-'+to;
	};
	</script>
</div>
