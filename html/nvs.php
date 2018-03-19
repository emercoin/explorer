<?php
require_once __DIR__ . '/../tools/include.php';
$query="SELECT MAX(height) AS height, MAX(time) AS time FROM blocks";
$result = $dbconn->query($query);
while($row = $result->fetch_assoc())
{
	$height=$row['height'];
	$block_time=$row['time'];
}

$query="SELECT MAX(id) AS id FROM nvs";
$result = $dbconn->query($query);
while($row = $result->fetch_assoc())
{
	$nvs_id=$row['id'];
}

$type="";
$name="";
$value="";
$results=25;
$show_na=0;
$show_valid=1;
$index=0;
if (isset($_SERVER['REQUEST_URI'])) {
	$URI=explode('/',$_SERVER['REQUEST_URI']);
	if ($URI[1]=="nvs") {
		if (isset($URI[2])) {
			$type=urldecode($URI[2]);
			$type=str_replace('&\&','/',$type);
			if (isset($URI[3])) {
				$name=urldecode($URI[3]);
				$name=str_replace('&\&','/',$name);
				if (isset($URI[4])) {
					$value=urldecode($URI[4]);
					$value=str_replace('&\&','/',$value);
					if (isset($URI[5])) {
						$results=urldecode($URI[5]);
						if ($results!="25"&&$results!="50"&&$results!="100"&&$results!="all") {
							$results="25";
						}
						if (isset($URI[6])) {
							$show_na=urldecode($URI[6]);
							if ($show_na!="0"&&$show_na!="1") {
								$show_na=0;
							}
							if (isset($URI[7])) {
								$show_valid=urldecode($URI[7]);
								if ($show_valid!="0"&&$show_valid!="1") {
									$show_valid=0;
								}
								if (isset($URI[8])) {
									$index=urldecode($URI[8]);
								}
							}
						}
					}
				}
			}
		}
	}
}

if ($type=="N/A"||$type=="n/a") {
	$show_na=1;
}

?>

<style type="text/css">
 a:hover {
  cursor:pointer;
 }
</style>

<div class="container">

<div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
  <div class="panel panel-default">
    <div class="panel-heading" role="tab" id="headingOne">
      <h4 class="panel-title">
        <a data-toggle="collapse" data-parent="#accordion" href="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
          <?php echo lang('SEARCH_NVS'); ?>
        </a>
      </h4>
    </div>
    <div id="collapseOne" class="panel-collapse collapse in" role="tabpanel" aria-labelledby="headingOne">
     <div class="panel-body">
     <form class="form-inline">
          <input type="text" id="inputType" size="10" class="form-control" placeholder="<?php echo lang('TYPE_TYPE'); ?>" value="<?php echo $type; ?>">
		  <input type="text" id="inputName" size="30" class="form-control" placeholder="<?php echo lang('NAME_NAME'); ?>" value="<?php echo $name; ?>">
		  <input type="text" id="inputValue" size="30" class="form-control" placeholder="<?php echo lang('VALUE_VALUENVS'); ?>" value="<?php echo $value; ?>">
          <a class="btn btn-primary" onclick="javascript:sendParameters(<?php echo "'".$results."'"; ?>, <?php echo $show_na; ?>, <?php echo $show_valid; ?>);" role="button"><?php echo lang('SEARCH_SEARCH'); ?></a>

		  <div class="btn-group">
		  <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
			<?php echo lang('RESULTS_PAGE'); ?> <?php echo $results; ?> <span class="caret"></span>
		  </button>
		  <ul class="dropdown-menu">
			<li><a onclick="javascript:sendParameters(25, <?php echo $show_na; ?>, <?php echo $show_valid; ?>);">25</a></li>
			<li><a onclick="javascript:sendParameters(50, <?php echo $show_na; ?>, <?php echo $show_valid; ?>);">50</a></li>
			<li><a onclick="javascript:sendParameters(100, <?php echo $show_na; ?>, <?php echo $show_valid; ?>);">100</a></li>
			<li role="separator" class="divider"></li>
			<li><a onclick="javascript:sendParameters('all', <?php echo $show_na; ?>, <?php echo $show_valid; ?>);"><?php echo lang('ALL_ALL'); ?></a></li>
		  </ul>
		</div>

		<button type="button" onclick="javascript:toogleShowNA(<?php echo "'".$results."'"; ?>, <?php echo $show_na; ?>, <?php echo $show_valid; ?>);" class="btn btn-default">
		<?php
			if ($show_na==0) {
				echo lang('N_HIDE');
			} else {
				echo lang('N_SHOW');
			}
		?>
		</button>

		<button type="button" onclick="javascript:toogleShowValid(<?php echo "'".$results."'"; ?>, <?php echo $show_na; ?>, <?php echo $show_valid; ?>);" class="btn btn-default">
		<?php
			if ($show_valid==0) {
				echo lang('VALID_DATED');
			} else {
				echo lang('VALID_ONLY');
			}
		?>
		</button>
	 </form>

	</div>
    </div>
  </div>
</div>

<script>

	$('#inputType').on('keyup', function(e) {
		if (e.which == 13) {
			sendParameters(<?php echo "'".$results."'"; ?>, <?php echo $show_na; ?>, <?php echo $show_valid; ?>);
		}
	});
	$('#inputName').on('keyup', function(e) {
		if (e.which == 13) {
			sendParameters(<?php echo "'".$results."'"; ?>, <?php echo $show_na; ?>, <?php echo $show_valid; ?>);
		}
	});
	$('#inputValue').on('keyup', function(e) {
		if (e.which == 13) {
			sendParameters(<?php echo "'".$results."'"; ?>, <?php echo $show_na; ?>, <?php echo $show_valid; ?>);
		}
	});

	function toogleShowNA(results,show_na,show_valid) {
		if (show_na==0) {show_na=1;}
		else if (show_na==1) {show_na=0;}
		sendParameters(results,show_na,show_valid);
	};

	function toogleShowValid(results,show_na,show_valid) {
		if (show_valid==0) {show_valid=1;}
		else if (show_valid==1) {show_valid=0;}
		sendParameters(results,show_na,show_valid);
	};

	function sendParameters(results,show_na,show_valid) {
		var type = document.getElementById('inputType').value;
		var type = type.replace(/\//g, "&\\&");
		var name = document.getElementById('inputName').value;
		var name = name.replace(/\//g, "&\\&");
		var value = document.getElementById('inputValue').value;
		var value = value.replace(/\//g, "&\\&");
		window.location.href = '/nvs/'+type+'/'+name+'/'+value+'/'+results+'/'+show_na+'/'+show_valid;
	};
</script>

<p>
	<table id="block_table" class="table table-striped tablesorter">
	<thead>
	<tr><th><?php echo lang('TYPE_TYPE'); ?></th><th><?php echo lang('NAME_NAME'); ?></th><th width="20px"></th><th><?php echo lang('VALUE_VALUENVS'); ?></th><th width="20px"></th><th width="125px"><?php echo lang('REGISTERED_AT'); ?></th><th width="100px"><?php echo lang('VALID_UNTIL'); ?></th></tr>
	</thead>
	<tbody>
	<?php
	$query="SELECT MAX(height) AS height FROM blocks";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$height=$row['height'];
	}

	$and="WHERE";
	$query="SELECT * FROM nvs ";
	$query2="SELECT COUNT(id) AS elements FROM nvs ";
	if ($show_na==0 && $show_valid==0) {
		$query.="WHERE type<>'' ";
		$query2.="WHERE type<>'' ";
		$and="AND";
	} else if ($show_na==1 && $show_valid==0) {
		$query.=" ";
		$query2.=" ";
	} else if ($show_na==0 && $show_valid==1) {
		$query.="WHERE type<>'' AND expires_at > '0' ";
		$query2.="WHERE type<>'' AND expires_at > '0' ";
		$and="AND";
	} else if ($show_na==1 && $show_valid==1) {
		$query.="WHERE expires_at > '0' ";
		$query2.="WHERE expires_at > '0' ";
		$and="AND";
	}

	if ($type!="" && $type!="N/A" && $type!="n/a") {
		$new_type=str_replace(' ','%',$type);
		$query.="$and type LIKE '%$new_type%' ";
		$query2.="$and type LIKE '%$new_type%' ";
		$and="AND";
	} else if ($type=="N/A"||$type=="n/a") {
		$query.="$and type = '' ";
		$query2.="$and type = '' ";
		$and="AND";
	}
	if ($name!="") {
		$new_name=str_replace(' ','%',$name);
		$query.="$and name LIKE '%$new_name%' ";
		$query2.="$and name LIKE '%$new_name%' ";
		$and="AND";
	}
	if ($value!="") {
		$new_value=str_replace(' ','%',$value);
		$query.="$and value LIKE '%$new_value%' ";
		$query2.="$and value LIKE '%$new_value%' ";
		$and="AND";
	}

	$query.="ORDER BY registered_at DESC ";

	if ($results!="all") {
		$limit=($index+$results);
		$query.="LIMIT $index, $limit";
	}

	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$isbinary=0;
		$type=$row['type'];
		if ($type=="") {$type="<i>N/A</i>";}
		$name=$row['name'];
		$value=utf8_decode($row['value']);
		$isbase64=$row['isbase64'];
		if ($isbase64==1) {
			$value=utf8_decode(base64_decode($value));
		}
		$value=htmlspecialchars($value);
		$divid=md5($name);
		$collapseLink='';
		if (strlen($value)>50) {
			$collapseLink='<a onclick="javascript:toggleText(\''.$divid.'\');"><div id="a'.$divid.'"><i class="fa fa-expand"></i></div></a>';

		}
		$valueshort='<div id="'.$divid.'short" class="show">'.substr($value, 0, 50).'</div>';
		$valuelong="";
		if (strlen($value)>50) {
			$valuelong='<div id="'.$divid.'long" class="hidden">'.$value.'</div>';
		}
		$registered_at=$row['registered_at'];
		$expires_at=$row['expires_at'];
		if ($expires_at<=0) {
			$isvalid="danger";
		} else {
			$isvalid="success";
		}
		if (preg_match('#^http:#i', $value) === 1||preg_match('#^https:#i', $value) === 1||preg_match('#^ftp:#i', $value) === 1||preg_match('#^sftp:#i', $value) === 1||preg_match('#^ftps:#i', $value) === 1||preg_match('#^magnet:#i', $value) === 1||preg_match('#^mailto:#i', $value) === 1||preg_match('#^emercoin:E#i', $value) === 1||preg_match('#^bitcoin:1#i', $value) === 1) {
			echo '<tr><td>'.$type.'</td><td>'.$name.'</td><td>'.$collapseLink.'</td><td>'.$valueshort.$valuelong.'</td><td><a target="_blank" href="'.$value.'"><i class="fa fa-external-link"></i></a></td><td><a href="/block/'.$registered_at.'" class="btn btn-primary btn-xs" role="button">'.$registered_at.'</a></td><td class="text-'.$isvalid.'">'.$expires_at.'</td></tr>';
		} else {
			echo '<tr><td>'.$type.'</td><td>'.$name.'</td><td>'.$collapseLink.'</td><td>'.$valueshort.$valuelong.'</td><td></td><td><a href="/block/'.$registered_at.'" class="btn btn-primary btn-xs" role="button">'.$registered_at.'</a></td><td class="text-'.$isvalid.'">'.$expires_at.'</td></tr>';
		}
	}
	?>
	</tbody>
	</table>

	<?php
	$elements=0;
	//get all elements
	$count_result = $dbconn->query($query2);
	while($row = $count_result->fetch_assoc())
	{
		$elements=$row['elements'];
	}
	?>

	<nav>
		<ul class="pager">
		<?php
			if ($limit<=$elements && $elements!=0) {
				echo '<li class="previous"><a href="javascript:sendPrevValues(\''.$results.'\','.$show_na.','.$show_valid.','.$index.');"><span aria-hidden="true"><i class="fa fa-arrow-circle-left"></i></span> '.lang('OLDER_OLDER').'</a></li>';
			}
			$index_new=($index-$results);
			if ($index_new>=0  && $elements!=0) {
				echo '<li class="next"><a href="javascript:sendNextValues(\''.$results.'\','.$show_na.','.$show_valid.','.$index.');">'.lang('NEWER_NEWER').' <span aria-hidden="true"><i class="fa fa-arrow-circle-right"></i></span></a></li>';
			}
		?>
		</ul>
	</nav>

	<script>
	function toggleText(divid) {
		if ($("#"+divid+"long").hasClass("hidden")) {
			$("#"+divid+"long").removeClass( "hidden" ).addClass( "show" );
			document.getElementById('a'+divid).innerHTML = '<i class="fa fa-compress"></i>';
		} else if ($("#"+divid+"long").hasClass("show")) {
			$("#"+divid+"long").removeClass( "show" ).addClass( "hidden" );
			document.getElementById('a'+divid).innerHTML = '<i class="fa fa-expand"></i>';
		}

		if ($("#"+divid+"short").hasClass("hidden")) {
			$("#"+divid+"short").removeClass( "hidden" ).addClass( "show" );
		} else if ($("#"+divid+"short").hasClass("show")) {
			$("#"+divid+"short").removeClass( "show" ).addClass( "hidden" );
		}
	}
	</script>

	<script>
	function sendPrevValues(results,show_na,show_valid,index) {
		var type = document.getElementById('inputType').value;
		var type = type.replace(/\//g, "&\\&");
		var name = document.getElementById('inputName').value;
		var name = name.replace(/\//g, "&\\&");
		var value = document.getElementById('inputValue').value;
		var value = value.replace(/\//g, "&\\&");
		var index = <?php if ($limit>$elements) {echo $elements;} else {echo $limit;} ?>;
		window.location.href = '/nvs/'+type+'/'+name+'/'+value+'/'+results+'/'+show_na+'/'+show_valid+'/'+index;
	};

	function sendNextValues(results,show_na,show_valid,index) {
		var type = document.getElementById('inputType').value;
		var type = type.replace(/\//g, "&\\&");
		var name = document.getElementById('inputName').value;
		var name = name.replace(/\//g, "&\\&");
		var value = document.getElementById('inputValue').value;
		var value = value.replace(/\//g, "&\\&");
		<?php
			if ($index_new<0) {$index_new=0;}
		?>
		var index = <?php echo $index_new; ?>;
		window.location.href = '/nvs/'+type+'/'+name+'/'+value+'/'+results+'/'+show_na+'/'+show_valid+'/'+index;
	};
	</script>
</div>

<script>
$(document).ready(function() {
    // call the tablesorter plugin
    $("#block_table").tablesorter({
     headers: {
		 0: { sorter: 'digit' },
		 3: { sorter: 'digit' },
		 4: { sorter: 'digit' }
     }
	});
});
</script>
