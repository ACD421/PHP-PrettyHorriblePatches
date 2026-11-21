<?php
echo "PHP_VERSION=" . PHP_VERSION . "\n";
$f = new SplFileObject("php://memory", "w+");
$f->fwrite("abc");
try {
    $s = serialize($f);
    echo "SER=" . $s . "\n";
    $u = unserialize($s);
    echo "UNSER class=" . get_class($u) . "\n";
    echo "READ=" . $u->fread(3) . "\n";
} catch (Throwable $e) {
    echo get_class($e) . ": " . $e->getMessage() . "\n";
}
echo "done\n";
