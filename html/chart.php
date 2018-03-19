<?php

?>
<div class="container">

<h3><?php echo lang('PER_CHARTS'); ?></h3><hr>

<button type="submit" class="btn btn-primry" id="coinsupply"><?php echo lang('COIN_SUPPLY'); ?> <i class="fa fa-caret-down"></i></button>
<div id="coinsupply_container" style="width:100%; height:375px; text-align:center;"></div><br><hr>
<script type="text/javascript">
$('#coinsupply_container').hide();
coinsupply=0;
$( "#coinsupply" ).click(function() {
	if (coinsupply==0) {
		$('#coinsupply_container').html('<i class="fa fa-spinner fa-3x fa-pulse"></i>');
		$('#coinsupply').html('<?php echo lang('COIN_SUPPLY'); ?> <i class="fa fa-caret-up"></i>');
		$('#coinsupply_container').show();
		get_coinsupply();
		coinsupply=1;
	} else {
		$('#coinsupply').html('<?php echo lang('COIN_SUPPLY'); ?> <i class="fa fa-caret-down"></i>');
		$('#coinsupply_container').hide(500);
		$('#coinsupply_container').html('');
		coinsupply=0;
	}
});

function get_coinsupply() {

    $.getJSON('/charts/get_coinsupply.php?callback=?', function (data) {
        // Create the chart
         $('#coinsupply_container').highcharts('StockChart', {

            rangeSelector : {
                selected : 1
            },

            title : {
                text : '<?php echo lang('COIN_SUPPLY'); ?>'
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


<button type="submit" class="btn btn-primry" id="powdifficulty"><?php echo lang('POW_DIFFICULTY'); ?> <i class="fa fa-caret-down"></i></button>
<div id="powdifficulty_container" style="width:100%; height:375px; text-align:center;"></div><br><hr>
<script type="text/javascript">
$('#powdifficulty_container').hide();
powdifficulty=0;
$( "#powdifficulty" ).click(function() {
	if (powdifficulty==0) {
		$('#powdifficulty_container').html('<i class="fa fa-spinner fa-3x fa-pulse"></i>');
		$('#powdifficulty').html('<?php echo lang('POW_DIFFICULTY'); ?> <i class="fa fa-caret-up"></i>');
		$('#powdifficulty_container').show();
		get_powdifficulty();
		powdifficulty=1;
	} else {
		$('#powdifficulty').html('<?php echo lang('POW_DIFFICULTY'); ?> <i class="fa fa-caret-down"></i>');
		$('#powdifficulty_container').hide(500);
		$('#powdifficulty_container').html('');
		powdifficulty=0;
	}
});

function get_powdifficulty() {

    $.getJSON('/charts/get_powdifficulty.php?callback=?', function (data) {
        // Create the chart
         $('#powdifficulty_container').highcharts('StockChart', {


            rangeSelector : {
                selected : 1
            },

            title : {
                text : '<?php echo lang('POW_DIFFICULTY'); ?>'
            },

            series : [{
                name : '<?php echo lang('POW_DIFF'); ?>',
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


<button type="submit" class="btn btn-primry" id="powhashrate">PoW Hashrate <i class="fa fa-caret-down"></i></button>
<div id="powhashrate_container" style="width:100%; height:375px; text-align:center;"></div><br><hr>
<script type="text/javascript">
$('#powhashrate_container').hide();
powhashrate=0;
$( "#powhashrate" ).click(function() {
	if (powhashrate==0) {
		$('#powhashrate_container').html('<i class="fa fa-spinner fa-3x fa-pulse"></i>');
		$('#powhashrate').html('PoW Hashrate <i class="fa fa-caret-up"></i>');
		$('#powhashrate_container').show();
		get_powhashrate();
		powhashrate=1;
	} else {
		$('#powhashrate').html('PoW Hashrate <i class="fa fa-caret-down"></i>');
		$('#powhashrate_container').hide(500);
		$('#powhashrate_container').html('');
		powhashrate=0;
	}
});

function get_powhashrate() {

    $.getJSON('/charts/get_powhashrate.php?callback=?', function (data) {
        // Create the chart
         $('#powhashrate_container').highcharts('StockChart', {


            rangeSelector : {
                selected : 1
            },

            title : {
                text : 'PoW Hashrate'
            },

            series : [{
                name : 'TH/s',
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


<button type="submit" class="btn btn-primry" id="posdifficulty"><?php echo lang('POS_DIFFICULTY'); ?> <i class="fa fa-caret-down"></i></button>
<div id="posdifficulty_container" style="width:100%; height:375px; text-align:center;"></div><br><hr>
<script type="text/javascript">
$('#posdifficulty_container').hide();
posdifficulty=0;
$( "#posdifficulty" ).click(function() {
	if (posdifficulty==0) {
		$('#posdifficulty_container').html('<i class="fa fa-spinner fa-3x fa-pulse"></i>');
		$('#posdifficulty').html('<?php echo lang('POS_DIFFICULTY'); ?> <i class="fa fa-caret-up"></i>');
		$('#posdifficulty_container').show();
		get_posdifficulty();
		posdifficulty=1;
	} else {
		$('#posdifficulty').html('<?php echo lang('POS_DIFFICULTY'); ?> <i class="fa fa-caret-down"></i>');
		$('#posdifficulty_container').hide(500);
		$('#posdifficulty_container').html('');
		posdifficulty=0;
	}
});

function get_posdifficulty() {

    $.getJSON('/charts/get_posdifficulty.php?callback=?', function (data) {
        // Create the chart
         $('#posdifficulty_container').highcharts('StockChart', {


            rangeSelector : {
                selected : 1
            },

            title : {
                text : '<?php echo lang('POS_DIFFICULTY'); ?>'
            },

            series : [{
                name : '<?php echo lang('POS_DIFF'); ?>',
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

<button type="submit" class="btn btn-primry" id="avgcoinage"><?php echo lang('AVG_AGE'); ?> <i class="fa fa-caret-down"></i></button>
<div id="avgcoinage_container" style="width:100%; height:375px; text-align:center;"></div><br><hr>
<script type="text/javascript">
$('#avgcoinage_container').hide();
avgcoinage=0;
$( "#avgcoinage" ).click(function() {
	if (avgcoinage==0) {
		$('#avgcoinage_container').html('<i class="fa fa-spinner fa-3x fa-pulse"></i>');
		$('#avgcoinage').html('<?php echo lang('AVG_AGE'); ?> <i class="fa fa-caret-up"></i>');
		$('#avgcoinage_container').show();
		get_avgcoinage();
		avgcoinage=1;
	} else {
		$('#avgcoinage').html('<?php echo lang('AVG_AGE'); ?> <i class="fa fa-caret-down"></i>');
		$('#avgcoinage_container').hide(500);
		$('#avgcoinage_container').html('');
		avgcoinage=0;
	}
});

function get_avgcoinage() {
    $.getJSON('/charts/get_avgcoinage.php?callback=?', function (data) {
        // Create the chart
         $('#avgcoinage_container').highcharts('StockChart', {


            rangeSelector : {
                selected : 1
            },

            title : {
                text : '<?php echo lang('AVG_AGE'); ?>'
            },

            series : [{
                name : '<?php echo lang('COIN_AGE'); ?>',
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

<button type="submit" class="btn btn-primry" id="coinage"><?php echo lang('TOTAL_AGE'); ?> <i class="fa fa-caret-down"></i></button>
<div id="coinage_container" style="width:100%; height:375px; text-align:center;"></div><br><hr>
<script type="text/javascript">
$('#coinage_container').hide();
coinage=0;
$( "#coinage" ).click(function() {
	if (coinage==0) {
		$('#coinage_container').html('<i class="fa fa-spinner fa-3x fa-pulse"></i>');
		$('#coinage').html('<?php echo lang('TOTAL_AGE'); ?> <i class="fa fa-caret-up"></i>');
		$('#coinage_container').show();
		get_coinage();
		coinage=1;
	} else {
		$('#coinage').html('<?php echo lang('TOTAL_AGE'); ?> <i class="fa fa-caret-down"></i>');
		$('#coinage_container').hide(500);
		$('#coinage_container').html('');
		coinage=0;
	}
});

function get_coinage() {
    $.getJSON('/charts/get_totalcoinage.php?callback=?', function (data) {
        // Create the chart
         $('#coinage_container').highcharts('StockChart', {


            rangeSelector : {
                selected : 1
            },

            title : {
                text : '<?php echo lang('TOTAL_AGE'); ?>'
            },

            series : [{
                name : '<?php echo lang('COIN_AGE'); ?>',
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


<button type="submit" class="btn btn-primry" id="addressinuse"><?php echo lang('USED_ADDRESSES'); ?> <i class="fa fa-caret-down"></i></button>
<div id="addressinuse_container" style="width:100%; height:375px; text-align:center;"></div><br><hr>
<script type="text/javascript">
$('#addressinuse_container').hide();
addressinuse=0;
$( "#addressinuse" ).click(function() {
	if (addressinuse==0) {
		$('#addressinuse_container').html('<i class="fa fa-spinner fa-3x fa-pulse"></i>');
		$('#addressinuse').html('<?php echo lang('USED_ADDRESSES'); ?> <i class="fa fa-caret-up"></i>');
		$('#addressinuse_container').show();
		get_addressinuse();
		addressinuse=1;
	} else {
		$('#addressinuse').html('<?php echo lang('USED_ADDRESSES'); ?> <i class="fa fa-caret-down"></i>');
		$('#addressinuse_container').hide(500);
		$('#addressinuse_container').html('');
		addressinuse=0;
	}
});

function get_addressinuse() {

    $.getJSON('/charts/get_usedaddresses.php?callback=?', function (data) {
        // Create the chart
         $('#addressinuse_container').highcharts('StockChart', {


            rangeSelector : {
                selected : 1
            },

            title : {
                text : '<?php echo lang('USED_ADDRESSES'); ?>'
            },

            series : [{
                name : '<?php echo lang('USED_ADDRESSES'); ?>',
				color: '#8d2d9e',
                data : data,
                tooltip: {
                    valueDecimals: 0
                }
            }],

			credits: {
				enabled: false
			},
        });
    });
};
</script>

<button type="submit" class="btn btn-primry" id="addressunused"><?php echo lang('UNUSED_ADDRESSES'); ?> <i class="fa fa-caret-down"></i></button>
<div id="addressunused_container" style="width:100%; height:375px; text-align:center;"></div><br><hr>
<script type="text/javascript">
$('#addressunused_container').hide();
addressunused=0;
$( "#addressunused" ).click(function() {
	if (addressunused==0) {
		$('#addressunused_container').html('<i class="fa fa-spinner fa-3x fa-pulse"></i>');
		$('#addressunused').html('<?php echo lang('UNUSED_ADDRESSES'); ?> <i class="fa fa-caret-up"></i>');
		$('#addressunused_container').show();
		get_addressunused();
		addressunused=1;
	} else {
		$('#addressunused').html('<?php echo lang('UNUSED_ADDRESSES'); ?> <i class="fa fa-caret-down"></i>');
		$('#addressunused_container').hide(500);
		$('#addressunused_container').html('');
		addressunused=0;
	}
});

function get_addressunused() {

    $.getJSON('/charts/get_unusedaddresses.php?callback=?', function (data) {
        // Create the chart
         $('#addressunused_container').highcharts('StockChart', {


            rangeSelector : {
                selected : 1
            },

            title : {
                text : '<?php echo lang('UNUSED_ADDRESSES'); ?>'
            },

            series : [{
                name : '<?php echo lang('UNUSED_ADDRESSES'); ?>',
				color: '#8d2d9e',
                data : data,
                tooltip: {
                    valueDecimals: 0
                }
            }],

			credits: {
				enabled: false
			},
        });
    });
};
</script>

<h3><?php echo lang('AVERAGE_CHARTS'); ?> <small><?php echo lang('DAILY_SUMMARY'); ?></small></h3><hr>

<button type="submit" class="btn btn-primry" id="transactions"><?php echo lang('TRANSACTIONS_TRANSACTIONS'); ?> <i class="fa fa-caret-down"></i></button> <i class="text-muted"><sub><?php echo lang('TX_ONLY'); ?></sub></i>
<div id="transactions_container" style="width:100%; height:375px; text-align:center;"></div><br><hr>
<script type="text/javascript">
$('#transactions_container').hide();
transactions=0;
$( "#transactions" ).click(function() {
	if (transactions==0) {
		$('#transactions_container').html('<i class="fa fa-spinner fa-3x fa-pulse"></i>');
		$('#transactions').html('<?php echo lang('TRANSACTIONS_TRANSACTIONS'); ?> <i class="fa fa-caret-up"></i>');
		$('#transactions_container').show();
		get_transactions();
		transactions=1;
	} else {
		$('#transactions').html('<?php echo lang('TRANSACTIONS_TRANSACTIONS'); ?> <i class="fa fa-caret-down"></i>');
		$('#transactions_container').hide(500);
		$('#transactions_container').html('');
		transactions=0;
	}
});

function get_transactions() {
    $.getJSON('/charts/get_transactions.php?callback=?', function (data) {
        // Create the chart
         $('#transactions_container').highcharts('StockChart', {


            rangeSelector : {
                selected : 1
            },

            title : {
                text : '<?php echo lang('TRANSACTIONS_TRANSACTIONS'); ?>'
            },

            series : [{
                name : 'Tx',
				color: '#8d2d9e',
                data : data,
                tooltip: {
                    valueDecimals: 0
                }
            }],

			credits: {
				enabled: false
			},
        });
    });
};
</script>

<button type="submit" class="btn btn-primry" id="dailycoindays"><?php echo lang('COIN_DESTROYED'); ?> <i class="fa fa-caret-down"></i></button> <i class="text-muted"><sub><?php echo lang('TX_ONLY'); ?></sub></i>
<div id="dailycoindays_container" style="width:100%; height:375px; text-align:center;"></div><br><hr>
<script type="text/javascript">
$('#dailycoindays_container').hide();
dailycoindays=0;
$( "#dailycoindays" ).click(function() {
	if (dailycoindays==0) {
		$('#dailycoindays_container').html('<i class="fa fa-spinner fa-3x fa-pulse"></i>');
		$('#dailycoindays').html('<?php echo lang('COIN_DESTROYED'); ?> <i class="fa fa-caret-up"></i>');
		$('#dailycoindays_container').show();
		get_dailycoindays();
		dailycoindays=1;
	} else {
		$('#dailycoindays').html('<?php echo lang('COIN_DESTROYED'); ?> <i class="fa fa-caret-down"></i>');
		$('#dailycoindays_container').hide(500);
		$('#dailycoindays_container').html('');
		dailycoindays=0;
	}
});

function get_dailycoindays() {
    $.getJSON('/charts/get_dailycoindays.php?callback=?', function (data) {
        // Create the chart
         $('#dailycoindays_container').highcharts('StockChart', {


            rangeSelector : {
                selected : 1
            },

            title : {
                text : '<?php echo lang('COIN_DESTROYED'); ?>'
            },

            series : [{
                name : '<?php echo lang('DAYS_DAYS'); ?>',
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

<button type="submit" class="btn btn-primry" id="timebetweenblocks"><?php echo lang('MINUTES_BLOCKS'); ?> <i class="fa fa-caret-down"></i></button>
<div id="timebetweenblocks_container" style="width:100%; height:375px; text-align:center;"></div><br><hr>
<script type="text/javascript">
$('#timebetweenblocks_container').hide();
timebetweenblocks=0;
$( "#timebetweenblocks" ).click(function() {
	if (timebetweenblocks==0) {
		$('#timebetweenblocks_container').html('<i class="fa fa-spinner fa-3x fa-pulse"></i>');
		$('#timebetweenblocks').html('<?php echo lang('MINUTES_BLOCKS'); ?> <i class="fa fa-caret-up"></i>');
		$('#timebetweenblocks_container').show();
		get_timebetweenblocks();
		timebetweenblocks=1;
	} else {
		$('#timebetweenblocks').html('<?php echo lang('MINUTES_BLOCKS'); ?> <i class="fa fa-caret-down"></i>');
		$('#timebetweenblocks_container').hide(500);
		$('#timebetweenblocks_container').html('');
		timebetweenblocks=0;
	}
});

function get_timebetweenblocks() {
    $.getJSON('/charts/get_timebetweenblocks.php?callback=?', function (data) {
        // Create the chart
         $('#timebetweenblocks_container').highcharts('StockChart', {


            rangeSelector : {
                selected : 1
            },

            title : {
                text : '<?php echo lang('MINUTES_BLOCKS'); ?>'
            },

            series : [{
                name : '<?php echo lang('MINUTES_MINUTES'); ?>',
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

<button type="submit" class="btn btn-primry" id="powposblocks"><?php echo lang('POW_BLOCKS'); ?> <i class="fa fa-caret-down"></i></button>
<div id="powposblocks_container" style="width:100%; height:375px; text-align:center;"></div><br><hr>
<script type="text/javascript">
$('#powposblocks_container').hide();
powposblocks=0;
$( "#powposblocks" ).click(function() {
	if (powposblocks==0) {
		$('#powposblocks_container').html('<i class="fa fa-spinner fa-3x fa-pulse"></i>');
		$('#powposblocks').html('<?php echo lang('POW_BLOCKS'); ?> <i class="fa fa-caret-up"></i>');
		$('#powposblocks_container').show();
		get_powposblocks();
		powposblocks=1;
	} else {
		$('#powposblocks').html('<?php echo lang('POW_BLOCKS'); ?> <i class="fa fa-caret-down"></i>');
		$('#powposblocks_container').hide(500);
		$('#powposblocks_container').html('');
		powposblocks=0;
	}
});

function get_powposblocks() {
    var seriesOptions = [],
        seriesCounter = 0,
        names = ['PoW', 'PoS'],
        // create the chart when all data is loaded
        createChart = function () {

            $('#powposblocks_container').highcharts('StockChart', {
				colors: ['#FA5858', '#01DF01'],

                rangeSelector: {
                    selected: 1
                },

				title : {
					text : '<?php echo lang('POW_BLOCKS'); ?>'
				},

				 yAxis: {
                    floor:0
                },

                tooltip: {
                    pointFormat: '<span style="color:{series.color}">{series.name}</span>: <b>{point.y}</b><br/>',
                    valueDecimals: 2
                },

                series: seriesOptions
            });
        };

    $.each(names, function (i, name) {

        $.getJSON('/charts/get_powposblocks.php?filename=' + name.toLowerCase() + '&callback=?',    function (data) {

            seriesOptions[i] = {
                name: name,
                data: data
            };
            seriesCounter += 1;

            if (seriesCounter === names.length) {
                createChart();
            }
        });
    });
};
</script>

<button type="submit" class="btn btn-primry" id="powposmint"><?php echo lang('POW_MINTED'); ?> <i class="fa fa-caret-down"></i></button>
<div id="powposmint_container" style="width:100%; height:375px; text-align:center;"></div><br><hr>
<script type="text/javascript">
$('#powposmint_container').hide();
powposmint=0;
$( "#powposmint" ).click(function() {
	if (powposmint==0) {
		$('#powposmint_container').html('<i class="fa fa-spinner fa-3x fa-pulse"></i>');
		$('#powposmint').html('<?php echo lang('POW_MINTED'); ?> <i class="fa fa-caret-up"></i>');
		$('#powposmint_container').show();
		get_powposmint();
		powposmint=1;
	} else {
		$('#powposmint').html('<?php echo lang('POW_MINTED'); ?> <i class="fa fa-caret-down"></i>');
		$('#powposmint_container').hide(500);
		$('#powposmint_container').html('');
		powposmint=0;
	}
});

function get_powposmint() {
    var seriesOptions = [],
        seriesCounter = 0,
        names = ['PoW', 'PoS'],
        // create the chart when all data is loaded
        createChart = function () {

            $('#powposmint_container').highcharts('StockChart', {
				colors: ['#FA5858', '#01DF01'],

                rangeSelector: {
                    selected: 1
                },

				title : {
					text : '<?php echo lang('POW_MINTED'); ?>'
				},

				 yAxis: {
                    floor:0
                },

                tooltip: {
                    pointFormat: '<span style="color:{series.color}">{series.name}</span>: <b>{point.y}</b><br/>',
                    valueDecimals: 2
                },

                series: seriesOptions
            });
        };

    $.each(names, function (i, name) {

        $.getJSON('/charts/get_powposmint.php?filename=' + name.toLowerCase() + '&callback=?',    function (data) {

            seriesOptions[i] = {
                name: name,
                data: data
            };
            seriesCounter += 1;

            if (seriesCounter === names.length) {
                createChart();
            }
        });
    });
};
</script>
</div>
