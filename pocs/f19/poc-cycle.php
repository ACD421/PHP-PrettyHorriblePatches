<?php
echo "PHP_VERSION=" . PHP_VERSION . "\n";
$a = new ArrayObject([1]);
$b = new ArrayObject([2]);
$a->exchangeArray($b);
$b->exchangeArray($a);
echo "linked\n";
$count = $a->count();
echo "count=$count\n";
