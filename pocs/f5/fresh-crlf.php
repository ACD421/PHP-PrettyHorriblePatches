<?php
echo "PHP " . PHP_VERSION . " crlf\n";
$fp = fopen("php://memory", "w+b");
fwrite($fp, str_repeat("A", 15) . " ");
rewind($fp);
stream_set_chunk_size($fp, 16);
stream_filter_append($fp, "convert.quoted-printable-encode", STREAM_FILTER_READ, [
    "line-break-chars" => "\r\n",
    "line-length" => 100000000,
]);
$d = stream_get_contents($fp);
echo "out=" . strlen((string)$d) . " tail=" . bin2hex(substr((string)$d, -4)) . "\n";
