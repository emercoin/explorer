<div class="container">
	<div class="row">
		<div class="col-md-4">
			<div id="latest_block" class="panel panel-default">

			</div>
			<div id="current_statistics" class="panel panel-default">

			</div>
		</div>
		<div class="col-md-5">
			<div id="recent_blocks" class="panel panel-default">
				<tr><td><i class="fa fa-spinner fa-3x fa-pulse"></i></td></tr>
			</div>
			<div id="recent_transactions" class="panel panel-default">
				<tr><td><i class="fa fa-spinner fa-3x fa-pulse"></i></td></tr>
			</div>
		</div>
		<div class="col-md-3">
			<div id="unconfirmed_transactions" class="panel panel-default">
				<tr><td><i class="fa fa-spinner fa-3x fa-pulse"></i></td></tr>
			</div>
			<div id="version_share" class="panel panel-default">
				<tr><td><i class="fa fa-spinner fa-3x fa-pulse"></i></td></tr>
			</div>
			<div id="explorer_status" class="panel panel-default">
				<tr><td><i class="fa fa-spinner fa-3x fa-pulse"></i></td></tr>
			</div>
		</div>
	</div>
</div>

<script>
$( document ).ready(function() {
	getLatestBlock();
	getCurrentStatistics();
	getRawMempool();
	getRecentBlocks();
	getRecentTransactions();
	getVersionShare();
	getExplorerStatus();
});

function getRawMempool() {
	$.ajax({
	url: "/ajax/get_rawmempool.php"
	})
	.done(function( html ) {
		$('#unconfirmed_transactions').html(html);
	});
	setTimeout(getRawMempool, 10000);
}

function getRecentTransactions() {
	$.ajax({
	url: "/ajax/get_recenttx.php"
	})
	.done(function( html ) {
		$('#recent_transactions').html(html);
	});
	setTimeout(getRecentTransactions, 15000);
}

function getRecentBlocks() {
	$.ajax({
	url: "/ajax/get_recentblocks.php"
	})
	.done(function( html ) {
		$('#recent_blocks').html(html);
	});
	setTimeout(getRecentBlocks, 15000);
}

function getLatestBlock()
{
	$.ajax({
	url: "/ajax/get_latest_block.php"
	})
	.done(function( html ) {
		$('#latest_block').html(html);
	});
	setTimeout(getLatestBlock, 15000);
}

function getCurrentStatistics()
{
	$.ajax({
	url: "/ajax/get_current_statistics.php"
	})
	.done(function( html ) {
		$('#current_statistics').html(html);
	});
	setTimeout(getCurrentStatistics, 15000);
}

function getVersionShare()
{
	$.ajax({
	url: "/ajax/get_versionshare.php"
	})
	.done(function( html ) {
		$('#version_share').html(html);
	});
	setTimeout(getVersionShare, 30000);
}

function getExplorerStatus()
{
	$.ajax({
	url: "/ajax/get_explorer_status.php"
	})
	.done(function( html ) {
		$('#explorer_status').html(html);
	});
	setTimeout(getExplorerStatus, 10000);
}

</script>
