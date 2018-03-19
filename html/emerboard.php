<?php
$query="SELECT MAX(height) AS height FROM blocks";
$result = $dbconn->query($query);
while($row = $result->fetch_assoc())
{	
	$height=$row['height'];
}
$diff=0;
$showtype="";
$channel="";
$type="";
$subject="";
$message="";
$index=0;
if (isset($_SERVER['REQUEST_URI'])) {
	$URI=explode('/',$_SERVER['REQUEST_URI']);
	if ($URI[1]=="emerboard") {
		if (isset($URI[2])) {
			$channel=urldecode($URI[2]);
			if (isset($URI[3])) {
				$type=urldecode($URI[3]);
				if (isset($URI[4])) {
					$subject=urldecode($URI[4]);
					if (isset($URI[5])) {
						$index=urldecode($URI[5]);
					}	
				}	
			}
		}
	}
}

  function isImage($url)
  {
     $params = array('http' => array(
                  'method' => 'HEAD'
               ));
     $ctx = stream_context_create($params);
     $fp = @fopen($url, 'rb', false, $ctx);
     if (!$fp) 
        return false;  // Problem with url

    $meta = stream_get_meta_data($fp);
    if ($meta === false)
    {
        fclose($fp);
        return false;  // Problem reading data from url
    }

    $wrapper_data = $meta["wrapper_data"];
    if(is_array($wrapper_data)){
      foreach(array_keys($wrapper_data) as $hh){
          if (substr($wrapper_data[$hh], 0, 19) == "Content-Type: image") // strlen("Content-Type: image") == 19 
          {
            fclose($fp);
            return true;
          }
      }
    }

    fclose($fp);
    return false;
  }
  


function makeClickableLinks($s) {
	$remove = array("\n", "\r\n", "\r");
	$s = str_replace($remove, ' <br> ', $s);
	$text=explode (' ',$s);
	foreach ($text as $s) {
		if (strpos($s,'http') !== false || strpos($s,'ftp') !== false) {
			if (isImage($s)) {
				echo " ".preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.-]*(\?\S+)?)?)?)@', '<a href="$1"><img style="height:auto; width:auto; max-width:75px; max-height:75px;" src="$1"></a>', $s);
			} else {
				echo " ".preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.-]*(\?\S+)?)?)?)@', '<a href="$1">$1</a>', $s);
			}
		} else {
			if (strpos($s,'@') !== false && strpos($s,'.') !== false) {
				$pattern = '#([0-9a-z]([-_.]?[0-9a-z])*@[0-9a-z]([-.]?[0-9a-z])*\\.';
				$pattern .= '[a-wyz][a-z](fo|g|l|m|mes|o|op|pa|ro|seum|t|u|v|z)?)#i';
				$replacement = '<a href="mailto:\\1">\\1</a>';
				$s = preg_replace($pattern, $replacement, $s);
			}
			echo " ".$s;
		}
	}
}


?>
<div class="container">

<div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
  <div class="panel panel-default">
    <div class="panel-heading" role="tab" id="headingOne">
      <h4 class="panel-title">
        <a data-toggle="collapse" data-parent="#accordion" href="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
          <?php echo lang('SPECIFY_CHANNEL'); ?>
        </a>
      </h4>
    </div>
    <div id="collapseOne" class="panel-collapse collapse in" role="tabpanel" aria-labelledby="headingOne">
      <div class="panel-body">
     <form class="form-inline">
	  <div class="form-group">
		<label for="inputChannel"></label>
		<input type="text" class="form-control" id="inputChannel" placeholder="<?php echo lang('CHANNEL_ADS'); ?>" value="<?php echo $channel; ?>">
		<label for="inputType"></label>
		<input type="text" class="form-control" id="inputType" placeholder="<?php echo lang('TYPE_BUY'); ?>" value="<?php echo $type; ?>">
		<label for="inputSubject"></label>
		<input type="text" class="form-control" size="35" id="inputSubject" placeholder="<?php echo lang('SUBJECT_PATTERN'); ?>" value="<?php echo $subject; ?>">
	  </div>
	  <a class="btn btn-primary" onclick="javascript:sendValues();" role="button"><?php echo lang('SEARCH_SEARCH'); ?></a>
	</form>

	</div>
    </div>
  </div>
</div>
	
	<script>
	
	$('#inputChannel').on('keyup', function(e) {
		if (e.which == 13) {
			sendValues();
		}
	});
	$('#inputType').on('keyup', function(e) {
		if (e.which == 13) {
			sendValues();
		}
	});
	$('#inputSubject').on('keyup', function(e) {
		if (e.which == 13) {
			sendValues();
		}
	});
	
	function sendValues() {
		var channel = document.getElementById('inputChannel').value;
		var type = document.getElementById('inputType').value;
		var subject = document.getElementById('inputSubject').value;
		window.location.href = '/emerboard/'+channel+'/'+type+'/'+subject;
	};
	</script>
	
<p>
	<?php 
	$channel=$dbconn->real_escape_string($channel);
	$type=$dbconn->real_escape_string($type);
	$subject=$dbconn->real_escape_string($subject);
	$index_diff=14;
	$limit=($index+$index_diff);
	if ($type=="" && $subject=="") {
		$query="SELECT name, value, isbase64
		FROM nvs
		WHERE type = '$channel' AND expires_at > $height
		ORDER BY registered_at DESC
		LIMIT $index, $limit";
		$query2="SELECT COUNT(name) AS elements
		FROM nvs
		WHERE type = '$channel' AND name AND expires_at > $height";
	} elseif ($type!="" && $subject=="") {
		$searchstring=$channel.':'.$type.':';
		$query="SELECT name, value, isbase64
		FROM nvs
		WHERE type = '$channel' AND name LIKE '$searchstring%' AND expires_at > $height
		ORDER BY registered_at DESC
		LIMIT $index, $limit";
		$query2="SELECT COUNT(name) AS elements
		FROM nvs
		WHERE type = '$channel' AND name LIKE '$searchstring%' AND expires_at > $height";
	} elseif ($type!="" && $subject!="") {
		$searchstring=$channel.':'.$type.':%';
		$new_subject=str_replace(' ','%',$subject);
		$searchstring.=$new_subject.'%';
		$query="SELECT name, value, isbase64
		FROM nvs
		WHERE type = '$channel' AND name LIKE '$searchstring' AND expires_at > $height
		ORDER BY registered_at DESC
		LIMIT $index, $limit";
		$query2="SELECT COUNT(name) AS elements
		FROM nvs
		WHERE type = '$channel' AND name LIKE '$searchstring' AND expires_at > $height";
	}
	
	$elements=0;
	//get all elements
	if ($channel!="") {
		$count_result = $dbconn->query($query2);
		while($row = $count_result->fetch_assoc())
		{
			$elements=$row['elements'];
		}
	}

	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{	
	    if (isset($row['name'])) {
			$message_name=$row['name'];
			$message_name_arr=explode(':',$message_name);
			$message_channel=$message_name_arr[0];
		}
		if (isset($message_name_arr[1])) {
			$message_type=$message_name_arr[1];
			if (isset($message_name_arr[2])) {
				$message_subject=$message_name_arr[2];
				if (trim($message_subject)!="") {
					$message_value=$row['value'];
					$isbase64=$row['isbase64'];
					if ($isbase64==1) {
						$message_value=base64_decode($message_value);
					}
					echo '<div class="panel panel-default">
						<div class="panel-heading"><a href="/emerboard/'.$message_channel.'/'.$message_type.'/'.$message_subject.'" rel="nofollow">'.$message_type.': '.$message_subject.'</a></div>
						<div class="panel-body">';
						echo makeClickableLinks($message_value);
						echo '</div>
					</div>';
				}	
			}
		}
	}
	?>

	
	
	
	<nav>
		<ul class="pager">
		<?php 
			if ($limit<=$elements && $elements!=0) {
				echo '<li class="previous"><a href="javascript:sendPrevValues();"><span aria-hidden="true"><i class="fa fa-arrow-circle-left"></i></span> Older</a></li>';
			}
			$index_new=($index-$index_diff);
			if ($index_new>=0  && $elements!=0) {
				echo '<li class="next"><a href="javascript:sendNextValues();">Newer <span aria-hidden="true"><i class="fa fa-arrow-circle-right"></i></span></a></li>';
			}
		?>
		</ul>
	</nav>
	
	<script>
	function sendPrevValues() {
		var channel = document.getElementById('inputChannel').value;
		var type = document.getElementById('inputType').value;
		var subject = document.getElementById('inputSubject').value;
		var index = <?php if ($limit>$elements) {echo $elements;} else {echo $limit;} ?>;
		window.location.href = '/emerboard/'+channel+'/'+type+'/'+subject+'/'+index;
	};
	
	function sendNextValues() {
		var channel = document.getElementById('inputChannel').value;
		var type = document.getElementById('inputType').value;
		var subject = document.getElementById('inputSubject').value;
		<?php
			if ($index_new<0) {$index_new=0;}
		?>
		var index = <?php echo $index_new; ?>;
		window.location.href = '/emerboard/'+channel+'/'+type+'/'+subject+'/'+index;
	};
	</script>
