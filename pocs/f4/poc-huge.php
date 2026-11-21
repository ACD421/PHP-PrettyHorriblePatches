<?php
echo "PHP " . PHP_VERSION . "\n";
$n = 2 * 1024 * 1024;
$fp = fopen("php://memory", "w+b");
fwrite($fp, str_repeat("A", $n - 1) . " ");
rewind($fp);
stream_set_chunk_size($fp, $n);
stream_filter_append($fp, "convert.quoted-printable-encode", STREAM_FILTER_READ, [
    "line-break-chars" => " \n",
    "line-length" => 100000000,
]);
echo "BEFORE\n";
$data = stream_get_contents($fp);
echo "AFTER " . (is_string($data) ? strlen($data) : -1) . " end=" . (is_string($data) ? bin2hex(substr($data, -4)) : "") . "\n";
echo "DONE\n";
