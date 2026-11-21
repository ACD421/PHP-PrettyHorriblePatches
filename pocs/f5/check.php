<?php
header('Content-Type: text/plain; charset=UTF-8');
$tag = $_GET['tag'] ?? 'x';
if (isset($_GET['clear'])) {
    set_time_limit(0);
}
$t0 = microtime(true);
$n = 0;
while ((microtime(true) - $t0) < 2.5) {
    $n++;
}
$dt = microtime(true) - $t0;
echo 'SURVIVED tag=' . $tag
    . ' dt=' . $dt
    . ' n=' . $n
    . ' met=' . ini_get('max_execution_time')
    . ' mit=' . ini_get('max_input_time')
    . "\n";
