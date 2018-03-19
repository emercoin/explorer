<?php
	$network=$_POST['network'];
	setcookie("network",$network,time()+(3600*24*14), "/");
?>