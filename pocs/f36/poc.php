<?php
// 1) mhash_keygen_s2k truncates the length through (int) before generating the key.
// 2) password_get_info() sscanf's Argon2 costs into zend_long and returns success
//    with a saturated cost when the digit run does not fit.
// 3) hash_copy() of an xxh3/xxh128 context created with a secret copies XXH3_state_t
//    by value, including extSecret, which points into the source context's secret
//    buffer. hash_final() on the source frees that buffer. The copy then reads it.

echo "PHP_VERSION=" . PHP_VERSION . "\n";

if (function_exists("mhash_keygen_s2k")) {
    $want = (1 << 32) + 16;
    $k = @mhash_keygen_s2k(MHASH_MD5, "password", "saltsalt", $want);
    $got = is_string($k) ? strlen($k) : -1;
    echo "mhash_keygen_s2k want=$want got=$got bad=" . (($got > 0 && $got !== $want) ? "YES" : "no") . "\n";
} else {
    echo "mhash_keygen_s2k absent\n";
}

if (defined("PASSWORD_ARGON2ID")) {
    $digits = str_repeat("9", 40);
    $hash = '$argon2id$v=19$m=' . $digits . ',t=4,p=1$c29tZXNhbHRzb21lc2FsdA$aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    $info = password_get_info($hash);
    $cost = $info["options"]["memory_cost"] ?? null;
    echo "argon_algo=" . $info["algoName"] . " memory_cost=" . var_export($cost, true)
        . " saturated=" . ($cost === PHP_INT_MAX ? "YES" : "no") . "\n";
    echo "argon_verify=" . var_export(password_verify("secret", $hash), true) . "\n";
    $nr = password_needs_rehash($hash, PASSWORD_ARGON2ID, [
        "memory_cost" => PHP_INT_MAX,
        "time_cost" => 4,
        "threads" => 1,
    ]);
    echo "argon_needs_rehash_matching_saturated=" . var_export($nr, true) . "\n";
} else {
    echo "argon2 absent\n";
}

$secret = str_repeat("S", 136);
$msg = str_repeat("M", 400);

foreach (["xxh3", "xxh128"] as $algo) {
    $fresh = hash_init($algo, options: ["secret" => $secret]);
    hash_update($fresh, $msg);
    $expect = hash_final($fresh);

    // Use the copy immediately after the source context is freed.
    // ASan poisons that block; extSecret still points into it.
    $ctx = hash_init($algo, options: ["secret" => $secret]);
    hash_update($ctx, $msg);
    $copy = hash_copy($ctx);
    hash_final($ctx);
    unset($ctx);
    $imm = hash_final($copy);
    echo "$algo immediate_after_free expect=$expect got=$imm mismatch=" . ($imm !== $expect ? "YES" : "no") . "\n";

    // Reuse the freed allocation, then digest again. The copy still reads extSecret.
    $ctx = hash_init($algo, options: ["secret" => $secret]);
    hash_update($ctx, $msg);
    $copy = hash_copy($ctx);
    hash_final($ctx);
    unset($ctx);
    $pad = [];
    for ($n = 64; $n <= 8192; $n += 64) {
        $pad[] = str_repeat("B", $n);
    }
    unset($pad);
    $got = hash_final($copy);
    echo "$algo after_reuse expect=$expect got=$got mismatch=" . ($got !== $expect ? "YES" : "no") . "\n";
}

echo "done\n";
