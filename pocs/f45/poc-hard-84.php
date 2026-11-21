<?php
fwrite(STDOUT, "PHP ".PHP_VERSION."\n");
fwrite(STDOUT, "memory_limit=".ini_get("memory_limit")."\n");
fwrite(STDOUT, "calling pack x300000000\n");
$s = pack("x300000000");
fwrite(STDOUT, "pack SURVIVED len=".strlen($s)."\n");
