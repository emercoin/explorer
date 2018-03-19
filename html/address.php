<?php
if (isset($_SERVER['REQUEST_URI'])) {
	$URI=explode('/',$_SERVER['REQUEST_URI']);
	if ($URI[1]=="address") {
		if (isset($URI[2])) {
			$address=$URI[2];
		}
	}
}

function TrimTrailingZeroes($nbr) {
    return strpos($nbr,'.')!==false ? rtrim(rtrim($nbr,'0'),'.') : $nbr;
}

echo '<div class="container">';
if (isset($address) && $address!="") {
	$query="SELECT balance, total_sent, total_received, count_sent, count_received
	FROM address
	WHERE address = '$address'
	";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{	
		$balance=round($row['balance'],8);
		$sent_count=$row['count_sent'];
		$sent_total=$row['total_sent'];
		$received_count=$row['count_received'];
		$received_total=$row['total_received'];
	}
	
	if (isset($received_count)) {
		echo '<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title">'.lang("BALANCE_BALANCE").' - '.$address.' - <button class="btn btn-xs btn-primary" type="button" id="addressbalance_button"><i class="fa fa-line-chart"></i></button> - <button class="btn btn-xs btn-primary" type="button" id="accountbalance_button"><i class="fa fa-bank"></i></button> - <button class="btn btn-xs btn-primary" type="button" id="coin_pos_button"><i class="fa fa-leaf"></i></button> - <button class="btn btn-xs btn-primary" type="button" id="qr_button"><i class="fa fa-qrcode"></i></button></h3>
				</div>
				<div class="panel-body">
					
					<div class="well" id="qr_well">
					</div>
					
					<div class="well" id="coin_pos_well">

					</div>
					
					<div class="well" id="accountbalance_well">

					</div>
						
					<div class="well" id="addressbalance_well">
					
					</div>
			
					<table class="table">
					<tr><td><h3>'.lang("BALANCE_BALANCE").'</h3></td><td width="75%"><h3><span class="label label-success">'.TrimTrailingZeroes(number_format($balance,8)).' EMC</span></h3></td></tr>
					<tr><td>'.lang("SENT_SENT").'</td><td><span class="label label-danger">'.TrimTrailingZeroes(number_format($sent_count,0)).' '.lang("INPUTS_INPUTS").' / '.TrimTrailingZeroes(number_format($sent_total,8)).' EMC</span></td></tr>
					<tr><td>'.lang("RECEIVED_RECEIVED").'</td><td><span class="label label-success">'.TrimTrailingZeroes(number_format($received_count,0)).' '.lang("OUTPUTS_OUTPUTS").' / '.TrimTrailingZeroes(number_format($received_total,8)).' EMC</span></td></tr>
					</table>
				</div>
				</div>';
				
		echo '<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title">'.lang("TRANSACTION_OVERVIEW").'</h3>
				</div>
				<div class="panel-body">

				<table id="tx_table" class="table table-striped tablesorter">
				<thead>
				<tr><th>'.lang("TX_ID").'</th><th>'.lang("TYPE_TYPE").'</th><th>'.lang("DATE_DATE").'</th><th>'.lang("VALUE_VALUE").' [EMC]</th><th>'.lang("BALANCE_BALANCE").' [EMC]</th></tr>
				</thead>
				<tbody>';	
				
		$query="SELECT tx.id, tx.txid, tx.time, vin.value AS sent, '' AS received
				FROM transactions AS tx
				INNER JOIN vin ON vin.parenttxid = tx.id
				WHERE vin.address = '$address'
				UNION ALL
				SELECT tx.id, tx.txid, tx.time, '' AS sent, vout.value AS received
				FROM transactions AS tx
				INNER JOIN vout ON vout.parenttxid = tx.id
				WHERE vout.address = '$address'
				ORDER BY id DESC";
		$result = $dbconn->query($query);
		$value=0;
		$countsent=0;
		$countreceived=0;
		$count=0;
		while($row = $result->fetch_assoc())
		{	
			$tx_id=$row['txid'];
			if(!isset($oldid)) {
				$oldid=$row['txid'];
				$tx_id=$oldid;
			}
			if ($oldid!=$tx_id) {
				$tx_id_short = substr($oldid, 0, 4)."...".substr($oldid, -4);
				
				echo '<tr><td><a href="/tx/'.$oldid.'" class="btn btn-primary btn-xs" role="button">'.$tx_id_short.'</a></td><td>'.$type.'</td><td>'.$time.'</td><td>'.TrimTrailingZeroes(number_format($value,8)).'</td><td>'.TrimTrailingZeroes(number_format($balance,8)).'</td></tr>';
				$balance=round(bcsub($balance,$value,8),8);
				$value=0;
				$countsent=0;
				$countreceived=0;
				$oldid=$tx_id;
			}
			
			$time=date("Y-m-d G:i:s e",$row['time']);
			if ($row['received']!="") {
				$value=round(bcadd($value,$row['received'],8),8);
				$countreceived++;
			} 
			if ($row['sent']!="") {
				$value=round(bcsub($value,$row['sent'],8),8);
				$countsent++;
			}	
			if ($countsent>0 && $countreceived==0) {
				$type=lang("SENT_SENT");
			}
			if ($countsent==0 && $countreceived>0) {
				$type=lang("RECEIVED_RECEIVED");
			}	
			if ($countsent>0 && $countreceived>0) {
				$type=lang('SENT_SENT')."/".lang('RECEIVED_RECEIVED');
			}	
		}
		$tx_id_short = substr($tx_id, 0, 4)."...".substr($tx_id, -4);
		echo '<tr><td><a href="/tx/'.$tx_id.'" class="btn btn-primary btn-xs" role="button">'.$tx_id_short.'</a></td><td>'.$type.'</td><td>'.$time.'</td><td>'.TrimTrailingZeroes(number_format($value,8)).'</td><td>'.TrimTrailingZeroes(number_format($balance,8)).'</td></tr>';
		echo '</tbody>';
			echo '</table>  
					</div>
				</div>';
		echo '</div>
			</div>';
	} else {
		echo '<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title">'.lang("BALANCE_BALANCE").' - '.$address.' - <button class="btn btn-xs btn-primary" type="button" id="addressbalance_button"><i class="fa fa-line-chart"></i></button> - <button class="btn btn-xs btn-primary" type="button" id="accountbalance_button"><i class="fa fa-bank"></i></button> - <button class="btn btn-xs btn-primary" type="button" id="coin_pos_button"><i class="fa fa-leaf"></i></button> - <button class="btn btn-xs btn-primary" type="button" id="qr_button"><i class="fa fa-qrcode"></i></button></h3>
				</div>
				<div class="panel-body">
					
					<div class="well" id="qr_well">
					</div>
					
					<div class="well" id="coin_pos_well">

					</div>
					
					<div class="well" id="accountbalance_well">

					</div>
						
					<div class="well" id="addressbalance_well">
					
					</div>
			
					<table class="table">
					<tr><td><h3>'.lang("BALANCE_BALANCE").'</h3></td><td width="75%"><h3><span class="label label-success">0 EMC</span></h3></td></tr>
					<tr><td>'.lang("SENT_SENT").'</td><td><span class="label label-danger">0 '.lang("INPUTS_INPUTS").' / 0 EMC</span></td></tr>
					<tr><td>'.lang("RECEIVED_RECEIVED").'</td><td><span class="label label-success">0 '.lang("OUTPUTS_OUTPUTS").' / 0 EMC</span></td></tr>
					</table>
				</div>
				</div>';
				
		echo '<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title">'.lang("TRANSACTION_OVERVIEW").'</h3>
				</div>
				<div class="panel-body">

				<table id="tx_table" class="table table-striped tablesorter">
				<thead>
				<tr><th>'.lang("TX_ID").'</th><th>'.lang("TYPE_TYPE").'</th><th>'.lang("DATE_DATE").'</th><th>'.lang("VALUE_VALUE").' [EMC]</th><th>'.lang("BALANCE_BALANCE").' [EMC]</th></tr>
				</thead>
				<tbody>';	
		echo '</tbody>';
			echo '</table>  
					</div>
				</div>';
		echo '</div>
			</div>';
	}
} else {
	echo '<h3>'.lang("NO_PROVIDED").'</h3>';
}
echo '</div>';
?>
<script>
$(document).ready(function() { 
    // call the tablesorter plugin 
    $("#tx_table").tablesorter({
     headers: {
         3: { sorter: 'digit' },
		 4: { sorter: 'digit' }
     }
	}); 
}); 

$(document).ready(function() { 
	$('#qr_well').hide();
	qr_pos=0;
	$( "#qr_button" ).click(function() {
		if (qr_pos==0) {
			$('#qr_well').show();
			get_coin_pos();
			qr_pos=1;
		} else {
			$('#qr_well').hide();
			qr_pos=0;
		}
	});
	
	var qrcode = new QRCode("qr_well", {
    text: "emercoin:<?php echo $address ?>",
    width: 192,
    height: 192,
    colorDark : "#000000",
    colorLight : "#ffffff",
    correctLevel : QRCode.CorrectLevel.H
});
	

}); 

$(document).ready(function() { 
	$('#coin_pos_well').hide();
	coin_pos=0;
	$( "#coin_pos_button" ).click(function() {
		if (coin_pos==0) {
			$('#coin_pos_well').html('<div class="text-center"><i class="fa fa-spinner fa-3x fa-pulse"></i></div>');
			$('#coin_pos_well').show();
			get_coin_pos();
			coin_pos=1;
		} else {
			$('#coin_pos_well').hide();
			$('#coin_pos_well').html('');
			coin_pos=0;
		}
	});
}); 

$(document).ready(function() { 
	$('#accountbalance_well').hide();
	accountbalance=0;
	$( "#accountbalance_button" ).click(function() {
		if (accountbalance==0) {
			$('#accountbalance_well').html('<div class="text-center"><i class="fa fa-spinner fa-3x fa-pulse"></i></div>');
			$('#accountbalance_well').show();
			get_accountbalance();
			accountbalance=1;
		} else {
			$('#accountbalance_well').hide();
			$('#accountbalance_well').html('');
			accountbalance=0;
		}
	});
}); 

$(document).ready(function() { 
	$('#addressbalance_well').hide();
	addressbalance=0;
	$( "#addressbalance_button" ).click(function() {
		if (addressbalance==0) {
			$('#addressbalance_well').html('<i class="fa fa-spinner fa-3x fa-pulse"></i><div id="addressbalance_container" style="width:100%; height:375px; text-align:center;" ></div>');
			$('#addressbalance_well').show();
			get_addressbalance();
			$('#addressbalance_well').html('<div id="addressbalance_container" style="width:100%; height:375px; text-align:center;" ></div>');
			addressbalance=1;
		} else {
			$('#addressbalance_well').hide();
			$('#addressbalance_well').html('');
			addressbalance=0;
		}
	});
}); 

function get_coin_pos() {
	$.ajax({
	url: "/ajax/get_mintingchance.php?address=<?php echo $address; ?>",
	cache: false
	})
	.done(function( html ) {
		$('#coin_pos_well').html(html);
	});
}	

function get_accountbalance() {
	$.ajax({
	url: "/ajax/get_accountbalance.php?address=<?php echo $address; ?>",
	cache: false
	})
	.done(function( html ) {
		$('#accountbalance_well').html(html);
	});
}	

function get_addressbalance() {

    $.getJSON('/charts/get_addressbalance.php?address=<?php echo $address; ?>&callback=?', function (data) {
        // Create the chart
         $('#addressbalance_container').highcharts('StockChart', {

            rangeSelector : {
                selected : 1
            },

            title : {
                text : '<?php echo $address; ?>'
            },
			
            series : [{
                name : 'EMC',
				color: '#8d2d9e',
                data : data,
                tooltip: {
                    valueDecimals: 2
                }
            }],
			
			credits: {
				enabled: false
			},
        });
    });
};
</script>
