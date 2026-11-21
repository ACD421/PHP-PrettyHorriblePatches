<?php
echo "PHP " . PHP_VERSION . " mode=" . ($argv[1] ?? "vuln") . "\n";
$mode = $argv[1] ?? "vuln";
$params = ["line-break-chars" => " \n", "line-length" => 100000000];
$payload = str_repeat("A", 15) . " ";
if ($mode === "crlf") {
    $params["line-break-chars"] = "\r\n";
} elseif ($mode === "binary") {
    $params["binary"] = true;
} elseif ($mode === "tab") {
    $params["line-break-chars"] = "\t\n";
    $payload = str_repeat("A", 15) . "\t";
} elseif ($mode === "nl") {
    $params["line-break-chars"] = "\n ";
}
$fp = fopen("php://memory", "w+b");
fwrite($fp, $payload);
rewind($fp);
stream_set_chunk_size($fp, strlen($payload));
stream_filter_append($fp, "convert.quoted-printable-encode", STREAM_FILTER_READ, $params);
$data = stream_get_contents($fp);
echo "out=" . strlen((string)$data) . " tail=" . bin2hex(substr((string)$data, -4)) . "\n";
