<?php
while(true) {
// sleep 20 sec and run again
sleep(20);
exec('php /var/www/emercoin-blockchain-explorer/tools/tget_blocks.php');
}
?>
