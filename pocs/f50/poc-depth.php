<?php
// json_encode checks max_depth only after recursing into children.
// A structure deeper than the requested depth should be rejected without
// crashing. Default depth is 512.
function nest(int $n) {
    $a = 1;
    for ($i = 0; $i < $n; $i++) {
        $a = [$a];
    }
    return $a;
}

foreach ([1000, 5000, 20000] as $n) {
    echo "encode depth-limit nest=$n\n";
    $a = nest($n);
    $r = json_encode($a, 0, 512);
    echo "ret=", var_export($r !== false, true), " err=", json_last_error(), " ", json_last_error_msg(), " len=", ($r === false ? -1 : strlen($r)), "\n";
}
echo "depth-done\n";
