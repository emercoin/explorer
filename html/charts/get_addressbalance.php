<?php
error_reporting(E_ALL);
require_once __DIR__ . '/../../tools/include.php';
$address=$_GET['address'];
$balance=0;
$query="SELECT tx.id, tx.txid, tx.time, vin.value AS sent, '' AS received
				FROM transactions AS tx
				INNER JOIN vin ON vin.parenttxid = tx.id
				WHERE vin.address = '$address'
				UNION ALL
				SELECT tx.id, tx.txid, tx.time, '' AS sent, vout.value AS received
				FROM transactions AS tx
				INNER JOIN vout ON vout.parenttxid = tx.id
				WHERE vout.address = '$address'
				ORDER BY id";
		$result = $dbconn->query($query);
		$value=0;
		echo $_GET["callback"];
		echo "(";
		$days_array = array();
		while($row = $result->fetch_assoc())
		{
			$tx_id=$row['txid'];
			if(!isset($oldid)) {
				$oldid=$row['txid'];
				$tx_id=$oldid;
			}
			$time=$row['time'];
			if(!isset($oldtime)) {
				$oldtime=$row['time'];
				$time=$oldtime;
			}
			if ($oldid!=$tx_id) {
				$balance=round(bcadd($balance,$value,8),8);

				$time_epoch =($oldtime * 1000);
				$day_array = array($time_epoch, round($balance,2));
				array_push($days_array, $day_array);

				$value=0;
				$oldid=$tx_id;
				$oldtime=$time;
			}
			if ($row['received']!="") {
				$value=round(bcadd($value,$row['received'],8),8);
			}
			if ($row['sent']!="") {
				$value=round(bcsub($value,$row['sent'],8),8);
			}
		}
		$balance=round(bcadd($balance,$value,8),8);
		$time_epoch =($oldtime * 1000);
		$day_array = array($time_epoch, round($balance,2));
		array_push($days_array, $day_array);

print json_encode($days_array, JSON_NUMERIC_CHECK);
echo ");";
?>
