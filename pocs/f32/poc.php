<?php
/* DFA rewrites ($i++ < $n) into (++$i <= $n) and ($n < $i--) into ($n <= --$i)
 * whenever the old value is long|double. That equivalence holds for integers
 * that do not overflow, not for floats and not for PHP_INT_MAX. */
function post_inc_lt(float $i, float $n): bool {
    return $i++ < $n;
}
function post_dec_gt(float $i, float $n): bool {
    return $n < $i--;
}
function post_inc_lt_int(int $i, int $n): bool {
    return $i++ < $n;
}
function post_inc_if(float $i, float $n): string {
    if ($i++ < $n) {
        return 'yes';
    }
    return 'no';
}

echo "float_lt ";
var_export(post_inc_lt(1.5, 2.0));
echo "\nfloat_dec ";
var_export(post_dec_gt(1.5, 1.0));
echo "\nint_max ";
var_export(post_inc_lt_int(PHP_INT_MAX, PHP_INT_MAX));
echo "\nif_branch ";
echo post_inc_if(1.5, 2.0);
echo "\n";
