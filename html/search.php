<?php 
if (isset($_SERVER['REQUEST_URI'])) {
	$URI=explode('/',$_SERVER['REQUEST_URI']);
	if ($URI[1]=="search") {
		if (isset($URI[2])) {
			if ($URI[2]!="") {
				$search=$URI[2];
				if (is_numeric($search)) {
					echo '<script type="text/javascript">
					   window.location = "/block/'.$search.'"
					</script>';
					exit;
				}
				else if (substr( $search, 0, 1 ) === "E") {
					echo '<script type="text/javascript">
					   window.location = "/address/'.$search.'"
					</script>';
					exit;
				}
				else {
					$query="SELECT txid FROM transactions WHERE txid='$search'";
					$result = $dbconn->query($query);
					while($row = $result->fetch_assoc())
					{
						$txidhash=$row['txid'];
						if (isset($txidhash)) {
							echo '<script type="text/javascript">
								window.location = "/tx/'.$search.'"
							</script>';
							exit;
						}
					}
					$query="SELECT hash FROM blocks WHERE hash='$search'";
					$result = $dbconn->query($query);
					while($row = $result->fetch_assoc())
					{
						$blockhash=$row['hash'];
						if (isset($blockhash)) {
							echo '<script type="text/javascript">
								window.location = "/block/'.$search.'"
							</script>';
							exit;
						}
					}
					echo '<div class="container"><h3>'.lang("PLEASE_HASH").'</h3></div>';
				}
			} else {
				echo '<div class="container"><h3>'.lang("PLEASE_HASH").'</h3></div>';
			}	
		} 
	}
}
?>
