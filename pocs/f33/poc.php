<?php
// Smallest IDN input that confuses a default host check.
// U+FF20 FULLWIDTH COMMERCIAL AT is not ASCII "@".
// idn_to_ascii() maps it to "@" and returns success (errors=0).
$in = "\u{FF20}a";
$info = [];
$r = idn_to_ascii($in, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46, $info);
echo "version=" . PHP_VERSION . "\n";
echo "in=" . bin2hex($in) . "\n";
echo "ret=" . (is_string($r) ? bin2hex($r) : var_export($r, true)) . "\n";
echo "errors=" . ($info['errors'] ?? 'x') . "\n";
if (is_string($r)) {
    $p = parse_url('http://' . $r . '/x');
    echo "host=" . var_export($p['host'] ?? null, true) . "\n";
    echo "user=" . var_export($p['user'] ?? null, true) . "\n";
}
$strict = [];
$s = idn_to_ascii($in, IDNA_USE_STD3_RULES, INTL_IDNA_VARIANT_UTS46, $strict);
echo "std3=" . (is_string($s) ? bin2hex($s) : var_export($s, true)) . " errors=" . ($strict['errors'] ?? 'x') . "\n";
echo "DONE\n";
