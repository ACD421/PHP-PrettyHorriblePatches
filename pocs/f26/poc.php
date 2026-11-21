<?php
// SQLite3 TEXT fetch uses ZVAL_STRING(), which stops at the first NUL.
// sqlite3_bind_text() stored the full length, and SQL length()/hex() still see it.
$db = new SQLite3(':memory:');
$db->exec('CREATE TABLE t(v TEXT)');
$stmt = $db->prepare('INSERT INTO t(v) VALUES (?)');
$stored = "VISIBLE\0HIDDEN_LEAK";
if (!$stmt->bindValue(1, $stored, SQLITE3_TEXT)) {
    fwrite(STDERR, "bind failed\n");
    exit(1);
}
$stmt->execute();

$sql_hex = $db->querySingle('SELECT hex(v) FROM t');
$got = $db->querySingle('SELECT v FROM t');
$fetched = $db->query('SELECT v FROM t')->fetchArray(SQLITE3_NUM);
$via_fetch = $fetched[0];
$full_hex = strtoupper(bin2hex($stored));

echo "stored_len=" . strlen($stored) . "\n";
echo "stored_hex=" . $full_hex . "\n";
echo "sql_hex=" . $sql_hex . "\n";
echo "querySingle_len=" . strlen($got) . "\n";
echo "querySingle_hex=" . bin2hex($got) . "\n";
echo "querySingle_eq_VISIBLE=" . ($got === "VISIBLE" ? "yes" : "no") . "\n";
echo "querySingle_has_hidden=" . (str_contains($got, "HIDDEN_LEAK") ? "yes" : "no") . "\n";
echo "fetch_len=" . strlen($via_fetch) . "\n";
echo "fetch_hex=" . bin2hex($via_fetch) . "\n";
echo "fetch_has_hidden=" . (str_contains($via_fetch, "HIDDEN_LEAK") ? "yes" : "no") . "\n";

// DB record keeps the bytes after 0x00 (hex() walks the stored value).
// PHP's fetch stops at the first NUL, so the tail never reaches userland.
if ($sql_hex === $full_hex
    && strlen($got) === 7
    && $got === "VISIBLE"
    && strlen($via_fetch) === 7
    && $via_fetch === "VISIBLE") {
    echo "BUG truncated TEXT at NUL\n";
    exit(0);
}
echo "NO_BUG\n";
exit(2);
