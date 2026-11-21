<?php
echo "PHP " . PHP_VERSION . "\n";
$n = 16;
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
echo "AFTER " . (is_string($data) ? strlen($data) : -1) . "\n";
if (is_string($data)) {
    echo "TAIL " . bin2hex(substr($data, -8)) . "\n";
}
echo "DONE\n";
