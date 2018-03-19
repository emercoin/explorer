<?php
error_reporting(E_ALL);
$dbconn = new mysqli("localhost", "explorer","<your password>", "explorer.emercoin.com128");
// Check connection
if ($dbconn->connect_error) {
    die("Connection failed: " . $dbconn->connect_error);
}

require_once '/var/www/emercoin-explorer/tools/include/jsonRPCClient.php';
$emercoin = new jsonRPCClient('http://<rpcuser>:<rpcpassword>@127.0.0.1:6662/');
?>
