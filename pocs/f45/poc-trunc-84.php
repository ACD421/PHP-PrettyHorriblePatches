<?php
fwrite(STDOUT, "PHP ".PHP_VERSION."\n");
$s = pack("x4294967301");
fwrite(STDOUT, "pack x4294967301 strlen=".strlen($s)."\n");
$s2 = pack("c4294967301", 65, 66, 67, 68, 69);
fwrite(STDOUT, "pack c4294967301 strlen=".strlen($s2)." hex=".bin2hex($s2)."\n");
$s3 = @pack("a2147483648", "hello");
fwrite(STDOUT, "pack a2147483648 strlen=".strlen($s3)." json=".json_encode($s3)."\n");
$s4 = @pack("x2147483648");
fwrite(STDOUT, "pack x2147483648 strlen=".strlen($s4)."\n");
$u = @unpack("a2147483648name", "hello");
fwrite(STDOUT, "unpack a2147483648 type=".gettype($u)."\n");
