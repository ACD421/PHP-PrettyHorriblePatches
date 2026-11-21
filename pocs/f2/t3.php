<?php
$r = exec('printf EXEC_OUT');
echo "RET=" . var_export($r, true) . "\n";
