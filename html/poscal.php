<?php
	$difficulty=$emercoin->getdifficulty();
	$posDifficulty=$difficulty['proof-of-stake'];
	$powDifficulty=$difficulty['proof-of-work'];
	$powReward=round(bcdiv(5020,bcsqrt(bcsqrt($powDifficulty,8),8),8),2)."<br>";
?>

<div class="container">
	<h2>Proof-of-Stake</h2>
	  <div class="row">
	    <div class="col-sm-offset-2 col-sm-8">
	      Coins <input type="input" class="form-control" id="inputCoins" placeholder="Coins">
	    </div>
	  </div>
	  <div class="row">
	    <div class="col-sm-offset-2 col-sm-8">
	      Days <input type="input" class="form-control" id="inputAge" placeholder="Age (days) [31-90]" value="31">
	    </div>
	  </div>
	  <div class="row">
	    <div class="col-sm-offset-2 col-sm-8">
	      PoS Difficulty <input type="input" class="form-control" id="inputDiff" placeholder="Difficulty" value="<?php echo $posDifficulty; ?>">
	    </div>
	  </div>
	  <br>
	  <div class="row">
	    <div class="col-sm-2">
	    </div>
		<div class="col-sm-8">
	    	<table class="table">
						<tr><th colspan="2"><?php echo lang("MINTING_CHANCE");?></th><th><?php echo lang("ESTIMATED_REWARD");?> [EMC]</th><tr>
						<tr><td id="mintChance10mHead" width="15%">31d +10m</td><td width="60%">
							<div class="progress">
  							<div id="mintChance10mTD" class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
    						-
  						</div>
							</div></td><td rowspan="4" id="rewardTD">-</td></tr>
						<tr><td id="mintChance1hHead" >31d +1h</td><td><div class="progress">
							<div id="mintChance1hTD" class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
							-
						</div></td></tr>
						<tr><td id="mintChance24hHead" >31d +24h</td><td><div class="progress">
							<div id="mintChance24hTD" class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
							-
						</div></td></tr>
						<tr><td id="mintChance30dHead" >31d +30d</td><td><div class="progress">
							<div id="mintChance30dTD" class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
							-
						</div></td></tr>
				</table>
	    </div>
	  </div>
	  <hr>
		<h2>Proof-of-Work - Merged Mining - EMC/BTC </h2>
		<div class="row">
	    <div class="col-sm-offset-2 col-sm-8">
	      Hashrate [TH/s] <input type="input" class="form-control" id="inputHashrate" placeholder="Hashrate [TH/s]">
	    </div>
	  </div>
	  <div class="row">
	    <div class="col-sm-offset-2 col-sm-8">
	      EMC PoW Difficulty <input type="input" class="form-control" id="inputPoWDiff" placeholder="EMC PoW-Difficulty" value="<?php echo $powDifficulty; ?>">
	    </div>
	  </div>
	  <div class="row">
	    <div class="col-sm-offset-2 col-sm-8">
	      BTC PoW Difficulty <input type="input" class="form-control" id="inputBTCPoWDiff" placeholder="BTC PoW-Difficulty" value="">
	    </div>
	  </div>
	  <br>
	  <div class="row">
	    <div class="col-sm-2">
	    </div>
		<div class="col-sm-8">
	    	<table class="table">
					<tr><th>Avg. time to find a EMC block</th><th>Est. reward per day [EMC]</th><th>PoW Block Reward [EMC]</th><tr>
					<tr><td id="powTimeToFindTD">-</td><td id="powPerDayTD">-</td><td id="powRewardTD"><?php echo $powReward; ?></td></tr>
				</table>
	    </div>
	  </div>
	  <div class="row">
	    <div class="col-sm-2">
	    </div>
		<div class="col-sm-8">
	    	<table class="table">
					<tr><th>Avg. time to find a BTC block</th><th>Est. reward per day [BTC]</th><th>PoW Block Reward [BTC]</th><tr>
					<tr><td id="powTimeToFindBTCTD">-</td><td id="powPerDayBTCTD">-</td><td id="powRewardBTCTD">12.5</td></tr>
				</table>
	    </div>
	  </div>

</div>

<script>
$('#inputCoins').on('keyup', function() {
	calculateProbBlockToday($('#inputAge').val(),$('#inputCoins').val(),$('#inputDiff').val());
});
$('#inputAge').on('keyup', function() {
	calculateProbBlockToday($('#inputAge').val(),$('#inputCoins').val(),$('#inputDiff').val());
});
$('#inputDiff').on('keyup', function() {
	calculateProbBlockToday($('#inputAge').val(),$('#inputCoins').val(),$('#inputDiff').val());
});

$('#inputHashrate').on('keyup', function() {
	calculatePowPerDay($('#inputHashrate').val(),$('#inputPoWDiff').val(),$('#inputBTCPoWDiff').val());
});$('#inputPoWDiff').on('keyup', function() {
	calculatePowPerDay($('#inputHashrate').val(),$('#inputPoWDiff').val(),$('#inputBTCPoWDiff').val());
});

/*function getBTCReward() {
	jQuery.ajaxSetup({async:false});
	$.get('https://blockchain.info/de/q/bcperblock',function(data){
		$('#powRewardBTCTD').html(data/100000000);
	});
	jQuery.ajaxSetup({async:true});
};*/

function getBTCDiff() {
	jQuery.ajaxSetup({async:false});
	$.get('https://blockexplorer.com/api/status?q=getDifficulty',function(data){
		$('#inputBTCPoWDiff').val(data['difficulty']);
	});
	jQuery.ajaxSetup({async:true});
};
window.onload = getBTCDiff;

function getProb(days, coins, difficulty) {
	var prob=0;
	if (days > 30) {
			var maxTarget = Math.pow(2, 224);
			var target = maxTarget/difficulty;
			var dayWeight = Math.min(days, 90)-30;
			prob = (target*coins*dayWeight)/Math.pow(2, 256);
	}
	return prob;
};
function calculateProbBlockToday(days, coins, difficulty) {
	var prob = getProb(days, coins, difficulty);
    var res = 1-(Math.pow((1-prob),600));
	res10m = res;
	res10m = res10m*100;
	var res10m = Math.round(res10m * 10000) / 10000;
	if (res10m > 100) {res10m=100;}
	var reward=0;
		if (days>30) {
			reward=((days*coins)/365)*0.06;
		}
	$('#mintChance10mTD').css('width', res10m+'%').attr('aria-valuenow', res10m).html(res10m+'%');
	$('#mintChance10mHead').html(days+'d +10m');
	var res1 = (1-(Math.pow((1-prob),3600)))*100;
	res1 = Math.round(res1 * 10000) / 10000;
	if (res1 > 100) {res1=100;}
	$('#mintChance1hTD').css('width', res1+'%').attr('aria-valuenow', res1).html(res1+'%');
	$('#mintChance1hHead').html(days+'d +1h');
	var res24 = (1-(Math.pow((1-prob),3600*24)))*100;
	res24 = Math.round(res24 * 10000) / 10000;
	var res24 = Math.round(res24 * 10000) / 10000;
	if (res24 > 100) {res24=100;}
	$('#mintChance24hTD').css('width', res24+'%').attr('aria-valuenow', res24).html(res24+'%');
	$('#mintChance24hHead').html(days+'d +24h');
	var res30 = (1-(Math.pow((1-prob),3600*24*30)))*100;
	res30 = Math.round(res30 * 10000) / 10000;
	var res30 = Math.round(res30 * 10000) / 10000;
	if (res30 > 100) {res30=100;}
	$('#mintChance30dTD').css('width', res30+'%').attr('aria-valuenow', res30).html(res30+'%');
	$('#mintChance30dHead').html(days+'d +30d');
	$('#rewardTD').html(Math.round(reward * 100) / 100);
};
function calculatePowPerDay(hashrate, difficulty, btcdiff) {
	if (difficulty < 1) {
		difficulty=1;
		$('#inputPoWDiff').val('1');
	}
	hashrate=hashrate*Math.pow(10,12);
	var powReward=5020/Math.sqrt(Math.sqrt(difficulty));
	var powTime=(difficulty*4294967296)/hashrate;
	var BTCpowTime=(btcdiff*4294967296)/hashrate;
	var rewardPerDay=(86400/powTime)*powReward;
	var BTCrewardPerDay=(86400/BTCpowTime)*$('#powRewardBTCTD').html();
	var powUnit;
	var BTCpowUnit;
	if (hashrate != "" || hashrate != 0) {
		if (powTime<60) {
			powUnit="s";
		} else if (powTime >=60 && powTime <3600) {
			powUnit="m";
			powTime=powTime/60;
		} else if (powTime >=3600 && powTime <86400) {
			powUnit="h";
			powTime=powTime/3600;
		}  else if (powTime >=86400) {
			powUnit="d";
			powTime=powTime/86400;
		}
		if (BTCpowTime<60) {
			BTCpowUnit="s";
		} else if (BTCpowTime >=60 && BTCpowTime <3600) {
			BTCpowUnit="m";
			BTCpowTime=BTCpowTime/60;
		} else if (BTCpowTime >=3600 && BTCpowTime <86400) {
			BTCpowUnit="h";
			BTCpowTime=BTCpowTime/3600;
		}  else if (BTCpowTime >=86400) {
			BTCpowUnit="d";
			BTCpowTime=BTCpowTime/86400;
		}
		$('#powTimeToFindTD').html(Math.round(powTime * 1) / 1+' '+powUnit);
		$('#powPerDayTD').html(Math.round(rewardPerDay * 1000000) / 1000000);
		$('#powRewardTD').html(Math.round(powReward * 100) / 100);
		$('#powTimeToFindBTCTD').html(Math.round(BTCpowTime * 1) / 1+' '+BTCpowUnit);
		$('#powPerDayBTCTD').html(Math.round(BTCrewardPerDay * 1000000) / 1000000);
	} else {
		$('#powTimeToFindTD').html('-');
		$('#powPerDayTD').html('-');
		$('#powRewardTD').html(Math.round(powReward * 100) / 100);
		$('#powTimeToFindBTCTD').html('-');
		$('#powPerDayBTCTD').html('-');
	}


};
</script>
