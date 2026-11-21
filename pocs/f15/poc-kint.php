<?php
/* \k<digits> uses strtoul() then casts to int.
 * 4294967297 is 2^32+1. On LP64 that becomes group 1, not an invalid ref. */
if (!function_exists('mb_ereg_replace')) {
    fwrite(STDERR, "no mbstring\n");
    exit(2);
}
echo 'php=' . PHP_VERSION . "\n";
$out = mb_ereg_replace('(X)(Y)', '[\k<4294967297>]', 'XY');
echo 'out=' . $out . "\n";
echo 'hex=' . bin2hex($out) . "\n";
$out2 = mb_ereg_replace('(X)(Y)', '[\k<4294967298>]', 'XY');
echo 'out2=' . $out2 . "\n";
echo 'hex2=' . bin2hex($out2) . "\n";
