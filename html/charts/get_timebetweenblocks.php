<?php
error_reporting(E_ALL);
if (explode('.', $_SERVER['HTTP_HOST'])[0] == "testnet") {
	require_once __DIR__ . '/../../tools/tinclude.php';
} else {
	require_once __DIR__ . '/../../tools/include.php';
}
$query="SELECT (
YEAR( FROM_UNIXTIME( `time` ) ) *3650 + MONTH( FROM_UNIXTIME( `time` ) ) *120 + DAY( FROM_UNIXTIME( `time` ) )
) AS `day` , FROM_UNIXTIME( time ) AS time, (1440/COUNT( hash )) AS blocks, COUNT( hash ) AS countblocks
FROM `blocks`
WHERE id > '1'
GROUP BY (
YEAR( FROM_UNIXTIME( `time` ) ) *3650 + MONTH( FROM_UNIXTIME( `time` ) ) *120 + DAY( FROM_UNIXTIME( `time` ) )
)
ORDER BY time";
$result = $dbconn->query($query);
echo $_GET["callback"];
echo "(";
$days_array = array();
while($row = $result->fetch_assoc())
{
	$timeArray=explode(' ', $row['time']);
	$timeString=$timeArray[0];
	$time_epoch=strtotime($timeString);
	$time_epoch =($time_epoch * 1000);
	$day_array = array($time_epoch, round($row['blocks'],2));
	$blocks = 1440/($row['countblocks']*bcdiv(86400,bcsub(time(),strtotime("today"),0),4));
	array_push($days_array, $day_array);
}
array_pop($days_array);
$day_array = array($time_epoch, round($blocks,2));
array_push($days_array, $day_array);
print json_encode($days_array, JSON_NUMERIC_CHECK);
echo ");";
?>
