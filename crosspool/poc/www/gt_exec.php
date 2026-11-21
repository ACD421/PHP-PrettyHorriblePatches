<?php
$r = exec('echo GT3_EXEC > /www/mk_exec.log');
echo "RET=".var_export($r, true)."\n";
echo "MK=".(file_exists('/www/mk_exec.log') ? trim(file_get_contents('/www/mk_exec.log')) : 'ABSENT')."\n";
