<?php
fwrite(STDOUT, "PHP ".PHP_VERSION."\n");
fwrite(STDOUT, "memory_limit=".ini_get("memory_limit")."\n");
fwrite(STDOUT, "calling sprintf width 400000000\n");
$s = sprintf("%400000000s", "");
fwrite(STDOUT, "sprintf SURVIVED len=".strlen($s)."\n");
