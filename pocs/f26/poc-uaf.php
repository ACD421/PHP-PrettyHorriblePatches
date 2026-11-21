<?php
// pdo_sqlite binds PARAM_STR / PARAM_LOB with SQLITE_STATIC.
// The statement keeps the PHP string pointer. Replacing the bound
// variable frees that buffer before fetch reads it back.
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->prepare('SELECT ? AS v');
$s = str_repeat('A', 128);
$stmt->bindParam(1, $s, PDO::PARAM_STR);
$stmt->execute();

// Drop the only owning zend_string. The sqlite cursor still points at it.
$s = str_repeat('B', 16);
unset($s);
gc_collect_cycles();

// Reuse the freed slot so a stale read is obvious if it does not crash.
$pad = [];
for ($i = 0; $i < 200; $i++) {
    $pad[] = "MARK{$i}_" . str_repeat('Z', 64);
}

$row = $stmt->fetch(PDO::FETCH_ASSOC);
$v = $row['v'] ?? '';
echo "len=" . strlen($v) . "\n";
echo "head=" . substr($v, 0, 32) . "\n";
echo "hex=" . bin2hex(substr($v, 0, 24)) . "\n";
echo "is_A=" . (str_starts_with($v, str_repeat('A', 32)) ? "yes" : "no") . "\n";
