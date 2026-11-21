<?php
echo "PHP " . PHP_VERSION . "\n";

function run_one(string $label, string $payload, array $params): void {
    $fp = fopen("php://memory", "w+b");
    fwrite($fp, $payload);
    rewind($fp);
    stream_set_chunk_size($fp, strlen($payload));
    stream_filter_append($fp, "convert.quoted-printable-encode", STREAM_FILTER_READ, $params);
    $data = stream_get_contents($fp);
    $len = is_string($data) ? strlen($data) : -1;
    $tail = is_string($data) ? bin2hex(substr($data, -6)) : "";
    echo $label . " in=" . strlen($payload) . " out=" . $len . " tail=" . $tail . "\n";
}

$params = ["line-break-chars" => " \n", "line-length" => 100000000];
$sp = str_repeat("A", 15) . " ";
$b = str_repeat("A", 15) . "B";
run_one("space-lb", $sp, $params);
run_one("space-lb-2", $sp, $params);
run_one("byteB", $b, $params);
run_one("space-nl-first", $sp, ["line-break-chars" => "\n ", "line-length" => 100000000]);
run_one("space-crlf", $sp, ["line-break-chars" => "\r\n", "line-length" => 100000000]);
run_one("space-binary", $sp, ["line-break-chars" => " \n", "line-length" => 100000000, "binary" => true]);
run_one("tab-lb", str_repeat("A", 15) . "\t", ["line-break-chars" => "\t\n", "line-length" => 100000000]);
