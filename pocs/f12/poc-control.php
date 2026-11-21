<?php
$spray = [];
for ($i = 0; $i < 4000; $i++) {
    $o = new stdClass;
    $o->n = $i;
    $o->s = str_repeat('A', 24);
    $spray[] = $o;
}
echo "control ", count($spray), "\n";
