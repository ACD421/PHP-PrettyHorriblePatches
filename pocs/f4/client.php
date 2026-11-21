<?php
$ctx = stream_context_create(["http" => ["timeout" => 30, "ignore_errors" => true]]);
$r = @file_get_contents("http://127.0.0.1:8080/poc.php", false, $ctx);
echo "CLIENT_BYTES " . (is_string($r) ? strlen($r) : "false") . "\n";
if (is_string($r)) {
    echo $r;
}
