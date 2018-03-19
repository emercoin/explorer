<?php
	$lang=$_POST['lang'];
	setcookie("lang",$lang,time()+(3600*24*14), "/");
?>