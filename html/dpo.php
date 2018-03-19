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

$brand="";
$sn="";
if (isset($_SERVER['REQUEST_URI'])) {
	$URI=explode('/',$_SERVER['REQUEST_URI']);
	if ($URI[1]=="dpo") {
		if (isset($URI[2])) {
			$brand=urldecode($URI[2]);
			$brand=str_replace('&\&','/',$brand);
			if (isset($URI[3])) {
				$sn=urldecode($URI[3]);
				$sn=str_replace('&\&','/',$sn);
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
	//$remove = array("\n", "\r\n", "\r");
	//$s = str_replace($remove, ' <br> ', $s);
	$org=$s;
	$text=explode (' ',$s);
	foreach ($text as $s) {
		if (strpos($s,'http') !== false || strpos($s,'ftp') !== false) {
			if (isImage($s)) {
				$s=preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.-]*(\?\S+)?)?)?)@', '<a href="$1"><img style="height:auto; width:auto; max-width:75px; max-height:75px;" src="$1"></a>', $s);
			} else {
				$s=preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.-]*(\?\S+)?)?)?)@', '<a href="$1">$1</a>', $s);
			}
			return $s;
		} else if (strpos($s,'@') !== false && strpos($s,'.') !== false) {
				$pattern = '#([0-9a-z]([-_.]?[0-9a-z])*@[0-9a-z]([-.]?[0-9a-z])*\\.';
				$pattern .= '[a-wyz][a-z](fo|g|l|m|mes|o|op|pa|ro|seum|t|u|v|z)?)#i';
				$replacement = '<a href="mailto:\\1">\\1</a>';
				$s = preg_replace($pattern, $replacement, $s);
				return $s;
			}
			return $org;
		}
}

function Tokenize($item) {
			$tokens  = array();
			$for_sig = array($item['name']);
			foreach(explode(PHP_EOL, $item['value']) as $val_line) {
			  if(substr($val_line, 0, 2) === "F-")
				array_push($for_sig, trim($val_line));
				$tok=explode("=", $val_line);
				if (strtolower($tok[0])=="signature") { $tok[1].="="; }
				$tokens[$tok[0]] = utf8_decode(trim($tok[1]));
			}
			$tokens['__FOR_SIG__'] =  join('|', $for_sig);
			return $tokens;
		}

function PrintTok($tokens) {
  echo "<pre>";
  foreach($tokens as $k => $v)
    if(substr($k, 0, 2) !== "__")
      echo "\t" . htmlspecialchars($k) . ": " .  makeClickableLinks(htmlspecialchars($v)) . "\n";
  echo "</pre>";
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
			  <?php echo 'Verify with emcDPO'; ?>
			</a>
		  </h4>
		</div>
		<div id="collapseOne" class="panel-collapse collapse in" role="tabpanel" aria-labelledby="headingOne">
		 <div class="panel-body">
		 <form class="form-inline">
			<input type="text" id="inputBrand" size="30" class="form-control" placeholder="<?php echo 'Brand'; ?>" value="<?php echo $brand; ?>">
			<input type="text" id="inputSN" size="30" class="form-control" placeholder="<?php echo 'Serial Number'; ?>" value="<?php echo $sn; ?>">

			<a class="btn btn-primary" onclick="javascript:sendParameters();" role="button"><?php echo 'Verify'; ?></a></button>
		 </form>

		</div>
		</div>
	  </div>
	</div>

	<script>

		$('#inputSN').on('keyup', function(e) {
			if (e.which == 13) {
				sendParameters();
			}
		});

		$('#inputBrand').on('keyup', function(e) {
			if (e.which == 13) {
				sendParameters();
			}
		});


		function sendParameters() {
			var brand = document.getElementById('inputBrand').value;
			var brand = brand.replace(/\//g, "&\\&");
			var sn = document.getElementById('inputSN').value;
			var sn = sn.replace(/\//g, "&\\&");
			window.location.href = '/dpo/'+brand+'/'+sn;
		};
	</script>

	<p>


	<?php
	if ($brand!='') {
		$brand_param = htmlspecialchars($brand);
		try {
			error_reporting(0);
			$brand_info=$emercoin->name_show('dpo:'.$brand);
			echo "<p><b>Brand info: $brand_param</b><br/>";
			$brandtok=Tokenize($brand_info);
			PrintTok($brandtok);
		} catch (Exception $e) {
			echo '<p><b>Brand "'.$brand_param.'" not found</b><br/><p>';
		}
	}

	if ($sn!='') {
		echo "<p><b>Serial: $sn</b></p>";
		$filt_key = 'dpo:'.$brand.':'.$sn.':';
		echo $filt_key."<br>";
		$filt_list = $emercoin->name_filter($filt_key);
		if(empty($filt_list)) {
		echo "Serial $sn not found in the Emercoin blockchain<br>";
		echo "<b>Verification: <font color='red'>FAILED</font></b>";
		} else
		foreach($filt_list as $item) { // Yterate item list
		  $item =  $emercoin->name_show($item['name']); // fetch full item record
		  $tokens = Tokenize($item);
		  echo "<p>Item: " . $item['name'] . "<br/>";
		  PrintTok($tokens);
		  $tokensCopy=array_change_key_case($tokens, CASE_LOWER);
			if (isset($tokensCopy['signature'])) {
			  try {
				$ver = $emercoin->verifymessage($brand_info['address'], $tokensCopy['signature'], $tokens['__FOR_SIG__'])?
				  "<font color='green'>PASSED</font><br><small class='text-muted'>(signature verified)</small>" : "<font color='red'>FAILED</font>";
				echo "<b>Verification: $ver</b>";
			  } catch(Exception $ex) {
				echo "<br></br><b>Verification: <font color='red'>FAILED</font></b><br>";
				//echo "Blockchain request error: ". $ex->getMessage() . "\n";
				echo "<small class='text-muted'>Blockchain request error - CALL: verifymessage '" . $brand_info['address']. "' '" . $tokensCopy['signature'] . "' '" . $tokens['__FOR_SIG__'] . "'</small><p>";
			   }
			  echo "</p>";
			} else {
				$history=$emercoin->name_history($item['name']);
				if ($brand_info['address']==$history[0]['address']) {
					 echo "<b>Verification: <font color='green'>PASSED</font></b><br><small class='text-muted'>(address verified)</small>";
				} else {
					echo "<b>Verification: <font color='red'>FAILED</font></b><br><small class='text-muted'></small>";
				}
			}
		} // foreach $filt_list
	}
	?>
	<div style="height:25px">
	</div>
</div>
