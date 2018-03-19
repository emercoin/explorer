<?php
error_reporting(E_ALL);
require_once __DIR__ . '/../../tools/include.php';
$query="SELECT time, COUNT( id ) AS blocks, AVG(difficulty) AS difficulty 
FROM blocks
WHERE id > 1 AND flags LIKE '%proof-of-work%'
GROUP BY CEIL((time)/3600)
ORDER BY time";
$result = $dbconn->query($query);
echo $_GET["callback"];
echo "(";
$days_array = array();
while($row = $result->fetch_assoc())
{
	$time_epoch =($row['time'] * 1000);
	$pow_difficulty=$row['difficulty'];
	$pow_blocks=$row['blocks'];
	$block_interval=bcdiv(3600,$pow_blocks,8);
	$current_pow_hashrate=bcdiv(bcmul($pow_difficulty,bcpow(2,32,8),8),$block_interval,8);
	$current_pow_hashrate=bcdiv($current_pow_hashrate,1000000000000,8); //to TH/s
	$day_array = array($time_epoch, round($current_pow_hashrate,2));
	array_push($days_array, $day_array);
}
print json_encode($days_array, JSON_NUMERIC_CHECK);
echo ");";
?>
