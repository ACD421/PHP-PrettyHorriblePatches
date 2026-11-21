<?php
echo "PHP " . PHP_VERSION . "\n";

function show(string $in): void {
    $e = null;
    $dt = date_create($in);
    $errs = date_get_last_errors();
    $errn = is_array($errs) ? (string) ($errs["error_count"] ?? 0) : "none";
    $warnn = is_array($errs) ? (string) ($errs["warning_count"] ?? 0) : "none";
    if ($dt instanceof DateTime) {
        echo "IN [" . $in . "] => " . $dt->format("c") . " Y=" . $dt->format("Y")
            . " off=" . $dt->getOffset() . " errors=" . $errn . " warnings=" . $warnn . "\n";
    } else {
        echo "IN [" . $in . "] => FALSE errors=" . $errn . " warnings=" . $warnn . "\n";
    }
}

show("2020-01-01 00:00:00 +00");
show("2020-01-01 00:00:00 +99");
show("2020-01-01 00:00:00 +999999");
show("2020-01-01 00:00:00 +9999");
echo "DONE\n";
