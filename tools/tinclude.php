<?php
error_reporting(E_ALL);
$dbconn = new mysqli("localhost", "explorer","<your password>", "texplorer.emercoin.com");
// Check connection
if ($dbconn->connect_error) {
    die("Connection failed: " . $dbconn->connect_error);
}

$dbconn2 = new mysqli("localhost", "explorer","<your password>", "texplorer.emercoin.com");
// Check connection
if ($dbconn2->connect_error) {
    die("Connection failed: " . $dbconn2->connect_error);
}

require_once '/var/www/emercoin-explorer/tools/include/jsonRPCClient.php';
$emercoin = new jsonRPCClient('http://<rpcuser>:<rpcpassword>@127.0.0.1:6664/');
?>
