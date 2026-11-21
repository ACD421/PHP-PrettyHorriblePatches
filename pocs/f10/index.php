<?php
$pi = $_SERVER["PATH_INFO"] ?? null;
$sn = $_SERVER["SCRIPT_NAME"] ?? null;
$sf = $_SERVER["SCRIPT_FILENAME"] ?? null;
echo "PI_LEN=" . (is_string($pi) ? strlen($pi) : -1) . "\n";
echo "PI_B64=" . (is_string($pi) ? base64_encode(substr($pi, 0, 400)) : "null") . "\n";
echo "SN_B64=" . (is_string($sn) ? base64_encode(substr($sn, 0, 200)) : "null") . "\n";
echo "SF=" . (is_string($sf) ? $sf : "null") . "\n";
echo "ORIG_SET=" . (isset($_SERVER["ORIG_PATH_INFO"]) ? "1" : "0") . "\n";
