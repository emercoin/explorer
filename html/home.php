<div class="container">
	<div class="row">
		<div id="home_info" class="jumbotron col-md-8">

		</div>
		<div class="col-md-4">
			<div id="unconfirmed_transactions"class="panel panel-default">
				<tr><td><i class="fa fa-spinner fa-3x fa-pulse"></i></td></tr>
			</div><br>
			<div id="recent_transactions"class="panel panel-default">
				<tr><td><i class="fa fa-spinner fa-3x fa-pulse"></i></td></tr>
			</div>
			<div id="version_share"class="panel panel-default">
				<tr><td><i class="fa fa-spinner fa-3x fa-pulse"></i></td></tr>
			</div>
		</div>
	</div>
</div>

<script>
$( document ).ready(function() {
	getHomeInfo();
	getRawMempool();
	getRecentTransactions();
	getVersionShare();
});

function getRawMempool()
{
	$.ajax({
	url: "/ajax/get_rawmempool.php"
	})
	.done(function( html ) {
		$('#unconfirmed_transactions').html(html);
	});
	setTimeout(getRawMempool, 10000);
}

function getRecentTransactions()
{
	$.ajax({
	url: "/ajax/get_recenttx.php"
	})
	.done(function( html ) {
		$('#recent_transactions').html(html);
	});
	setTimeout(getRecentTransactions, 15000);
}

function getHomeInfo()
{
	$.ajax({
	url: "/ajax/get_homeinfo.php"
	})
	.done(function( html ) {
		$('#home_info').html(html);
	});
	setTimeout(getHomeInfo, 15000);
}

function getVersionShare()
{
	$.ajax({
	url: "/ajax/get_versionshare.php"
	})
	.done(function( html ) {
		$('#version_share').html(html);
	});
	setTimeout(getVersionShare, 15000);
}

</script>
