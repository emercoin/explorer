<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once __DIR__ . '/include.php';

getnvsinfo($dbconn, $emercoin);

function getnvsinfo($dbconn, $emercoin) {
	$inputs="";
	$query="TRUNCATE TABLE nvs";

        if (!$result = $dbconn->query($query)) {
                printf("Errormessage_select: %s\n", $dbconn->error);
        }
/*	$valueindb=array();
	$query="SELECT * FROM nvs";

	if (!$result = $dbconn->query($query)) {
		printf("Errormessage_select: %s\n", $dbconn->error);
	}
	while($row = $result->fetch_assoc())
	{
		$valueindb[addslashes($row['name'])]['value']=addslashes($row['value']);
		$valueindb[addslashes($row['name'])]['type']=addslashes($row['type']);
		$valueindb[addslashes($row['name'])]['registered_at']=$row['registered_at'];
		$valueindb[addslashes($row['name'])]['expires_at']=$row['expires_at'];
	}
*/	
	$nvs=$emercoin->name_filter();
	$count=0;
	foreach ($nvs as $nv) {
		$name=addslashes($nv['name']);
		$type="";
		if (strpos($name, ':') !== false) {
			$nameArr=explode(':',$name);
			$type=addslashes($nameArr[0]);
		}
		$value=addslashes($nv['value']);
		$isbase64=0;
		if (!ctype_print($value)) {
			$isbase64=1;
			$value=base64_encode($value);
		}
		$registered_at=$nv['registered_at'];
		$expires_at=($height+$nv['expires_in']);
//		if (!array_key_exists($name,$valueindb)) {
				$inputs.="('$name', '$value', '$type', '$isbase64', '$registered_at', '$expires_at'),";
/*		} else {
				if ($value!=$valueindb[$name]['value']||$registered_at!=$valueindb[$name]['registered_at']||$expires_at!=$valueindb[$name]['expires_at']) {
				$query="UPDATE nvs
				SET value='$value',
				isbase64='$isbase64',
				registered_at='$registered_at',
				expires_at='$expires_at'
				WHERE name='$name'";
				if (!$dbconn->query($query)) {
					printf("Errormessage_update: %s\n", $dbconn->error);
				}
			}
		}
*/
		$count++;
		if ($count == 10000) {
			$inputs=rtrim($inputs, ",");
			$inputs.=";";
			$query="INSERT INTO nvs
			(name, value, type, isbase64, registered_at, expires_at)
			VALUES".$inputs;
			//echo $query;
			if (!$dbconn->query($query)) {
				printf("Errormessage_insert: %s\n", $dbconn->error);
			}
			$inputs='';
			$count=0;
		}
	}
	if ($inputs!='') {
		$inputs=rtrim($inputs, ",");
		$inputs.=";";
		$query="INSERT INTO nvs
		(name, value, type, isbase64, registered_at, expires_at)
		VALUES".$inputs;
		//echo $query;
		if (!$dbconn->query($query)) {
			printf("Errormessage_insert: %s\n", $dbconn->error);
		}
	}
};

?>
