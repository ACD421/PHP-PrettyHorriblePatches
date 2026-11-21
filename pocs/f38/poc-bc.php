<?php
echo "PHP " . PHP_VERSION . "\n";

$s = "1\x002";
try {
    $r = bcadd($s, "0");
    echo "bcadd NUL => [" . $r . "] strlen=" . strlen($s) . " is_numeric=" . (is_numeric($s) ? "yes" : "no") . "\n";
} catch (Throwable $e) {
    echo "bcadd NUL EX " . $e->getMessage() . "\n";
}

try {
    echo "bccomp 1NUL999 vs 2 => " . bccomp("1\x00999", "2") . "\n";
} catch (Throwable $e) {
    echo "bccomp NUL EX " . $e->getMessage() . "\n";
}

try {
    echo "bcadd frac 1.2NUL3 => [" . bcadd("1.2\x003", "0", 4) . "]\n";
} catch (Throwable $e) {
    echo "bcadd frac EX " . $e->getMessage() . "\n";
}

try {
    echo "bcadd 1a2 => [" . bcadd("1a2", "0") . "]\n";
} catch (Throwable $e) {
    echo "bcadd 1a2 EX " . $e->getMessage() . "\n";
}

try {
    echo "bcround MIN => [" . bcround("1.5", PHP_INT_MIN) . "]\n";
} catch (Throwable $e) {
    echo "bcround EX " . $e->getMessage() . "\n";
}

try {
    echo "bcsqrt => [" . bcsqrt("2", 10) . "]\n";
} catch (Throwable $e) {
    echo "bcsqrt EX " . $e->getMessage() . "\n";
}

try {
    $n = new BcMath\Number("-0.000");
    echo "num add scale => [" . $n->add("0.000", 2) . "] signstr=" . (string) $n . "\n";
} catch (Throwable $e) {
    echo "num EX " . $e->getMessage() . "\n";
}

echo "DONE\n";
