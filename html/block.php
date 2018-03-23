<?php
if (isset($_SERVER['REQUEST_URI'])) {
	$URI=explode('/',$_SERVER['REQUEST_URI']);
	if ($URI[1]=="block") {
		if (isset($URI[2])) {
			$hash=$URI[2];
		}
	}
}


echo '<div class="container">
<div id="blockDiv"><i class="fa fa-spinner fa-3x fa-pulse"></i></div>';

echo '<div id="txDiv"><i class="fa fa-spinner fa-3x fa-pulse"></i></div>';

echo '<div id="nvsDiv"><i class="fa fa-spinner fa-3x fa-pulse"></i></div>';

echo '</div>';
?>

<script>
$(document).ready(function() {
	$.ajax({
	url: "/ajax/block/get_block_id.php?hash=<?php echo $hash; ?>"
	})
	.done(function( html ) {
		block_id = html;
		if (block_id >= 0) {
			$.ajax({
			url: "/ajax/block/get_block.php?block_id="+block_id
			})
			.done(function( html ) {
				$('#blockDiv').html(html);
			});
			$.ajax({
			url: "/ajax/block/get_tx.php?block_id="+block_id
			})
			.done(function( html ) {
				$('#txDiv').html(html);
			});
			$.ajax({
			url: "/ajax/block/get_nvs.php?block_id="+block_id
			})
			.done(function( html ) {
				$('#nvsDiv').html(html);
			});
		} else {
				$('#blockDiv').html('<h3><?php echo lang("UNKNOWN_BLOCK"); ?></h3>')
			  $('#txDiv').html('');
				$('#nvsDiv').html('');
		}
	});
});
</script>
