	<?php
	error_reporting(E_ALL);
	ini_set("display_errors", 1);
if (explode('.', $_SERVER['HTTP_HOST'])[0] == "testnet") {
	require_once __DIR__ . '/../../../tools/tinclude.php';
} else {
	require_once __DIR__ . '/../../../tools/include.php';
}
if (!empty($_COOKIE["lang"])) {
	$lang=$_COOKIE["lang"];
	require("../../lang/".$lang.".php");
} else {
	setcookie("lang","en",time()+(3600*24*14), "/");
	require("../../lang/en.php");
}

function TrimTrailingZeroes($nbr) {
    return strpos($nbr,'.')!==false ? rtrim(rtrim($nbr,'0'),'.') : $nbr;
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

$block_id=mysqli_real_escape_string($dbconn, $_GET['block_id']);

if (isset($block_id) && $block_id!="") {
	echo '
	<div class="panel panel-default">
		<div class="panel-heading">
			<h3 class="panel-title">'.lang("NVS_NVS").'</h3>
		</div>
		<div class="panel-body">';
		$query = "SELECT height
	 	 FROM blocks
	 	 WHERE id = '$block_id'";
	  $result = $dbconn->query($query);
	  while($row = $result->fetch_assoc())
	  {
	 	  $height=$row['height'];
	  }
	$query = "SELECT * FROM nvs WHERE registered_at = '$height'";
	$result = $dbconn->query($query);
	while($row = $result->fetch_assoc())
	{
		$nvs_name=$row['name'];
		$nvs_value=$row['value'];
		$nvs_type=$row['type'];
		$nvs_isbase64=$row['isbase64'];
		if ($nvs_isbase64==1) {
			$nvs_value=utf8_decode(base64_decode($nvs_value));
		}
		$nvs_expires_at=$row['expires_at'];
		echo '<b>'.lang("NAME_NAME").':</b> '.$nvs_name.'<br>';
		try {
			error_reporting(0);
			$history=$emercoin->name_history($nvs_name);
			echo '<b>Value History:</b> <br>';
			$days_added=0;
			$initialtime=$history[0]['time'];
			foreach ($history as $element) {
				echo htmlspecialchars($element['value']).'<br>';
				$owner=$element['address'];
				$days_added+=$element['days_added'];
			}
			echo "<br><b>Owner:</b> ".$owner;
			$valid_until=date('d.m.Y', bcadd($initialtime,bcmul($days_added,86400,0),0));
			echo "<br><b>Valid until:</b> ".$valid_until.'<br>';
		} catch (Exception $e) {
			echo '<p><b>Name history is not available.</b><br/><p>';
			echo lang("VALUE_VALUE").': '.$nvs_value.'<br>';
		}

		if ($nvs_type=="dpo") {
			echo '<br><b>DPO</b><br>';
			$nameArr=explode(':',$nvs_name);
			$brand=$nameArr[1];
			$sn="";
			if ($nameArr[2]) {
				$sn=$nameArr[2];
			}
			if ($brand!='') {
				$brand_param = htmlspecialchars($brand);
				try {
					error_reporting(0);
					$brand_info=$emercoin->name_show('dpo:'.$brand);
					echo "<p><b>Brand info:$brand_parama</b><br/>";
					$brandtok=Tokenize($brand_info);
					PrintTok($brandtok);
				} catch (Exception $e) {
					echo '<p><b>Brand "'.$brand_param.'" not found</b><br/><p>';
				}
			}
			if ($sn!="") {
				$sn = preg_replace('/[^0-9A-Za-z_-]/', '', $sn);
				echo "<p><b>Serial: $sn</b></p>";
				$filt_key = 'dpo:'.$brand.':'.$sn.':';
				$filt_list = $emercoin->name_filter($filt_key);
				if(empty($filt_list)) {
				echo "Serial $sn not found in the Emercoin blockchain<br>";
				echo "<b>Verification: <font color='red'>FAILED</font></b>";
				} else
				foreach($filt_list as $item) { // Yterate item list
					$item =  $emercoin->name_show($item['name']); // fetch full item record
					$tokens = Tokenize($item);
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
							echo "<b>Verification: <font red='green'>FAILED</font></b><br><small class='text-muted'></small>";
						}
					}
				} // foreach $filt_list
			}
		}
		echo '<hr>';
	}
	echo '</div>';
}
?>
