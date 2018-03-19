<?php
error_reporting(E_ALL);
require_once __DIR__ . '/../../tools/include.php';
if ($_GET['filename']=="pow") {
$query="SELECT (
YEAR( FROM_UNIXTIME( `time` ) ) *3650 + MONTH( FROM_UNIXTIME( `time` ) ) *120 + DAY( FROM_UNIXTIME( `time` ) )
) AS `day` , FROM_UNIXTIME( time ) AS time, SUM( mint ) AS mint
FROM `blocks`
WHERE id > '1'
AND flags LIKE '%proof-of-work%'
GROUP BY (
YEAR( FROM_UNIXTIME( `time` ) ) *3650 + MONTH( FROM_UNIXTIME( `time` ) ) *120 + DAY( FROM_UNIXTIME( `time` ) )
)
ORDER BY time";
} else if ($_GET['filename']=="pos") {
$query="SELECT (
YEAR( FROM_UNIXTIME( `time` ) ) *3650 + MONTH( FROM_UNIXTIME( `time` ) ) *120 + DAY( FROM_UNIXTIME( `time` ) )
) AS `day` , FROM_UNIXTIME( time ) AS time, SUM( mint ) AS mint
FROM `blocks`
WHERE id > '1'
AND flags LIKE '%proof-of-stake%'
GROUP BY (
YEAR( FROM_UNIXTIME( `time` ) ) *3650 + MONTH( FROM_UNIXTIME( `time` ) ) *120 + DAY( FROM_UNIXTIME( `time` ) )
)
ORDER BY time";
}

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
	$day_array = array($time_epoch, round($row['mint'],2));
	array_push($days_array, $day_array);
}
print json_encode($days_array, JSON_NUMERIC_CHECK);
echo ");";
?>
