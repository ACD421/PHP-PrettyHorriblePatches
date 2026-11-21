<?php
echo "PHP ".PHP_VERSION."\n";

function show($label, $v) {
    echo $label, " type=", gettype($v);
    if (is_array($v)) {
        echo " keys=";
        foreach ($v as $k => $val) {
            echo "[", gettype($k), ":", var_export($k, true), "=>", var_export($val, true), "]";
        }
    } elseif (is_object($v)) {
        echo " props=";
        foreach (get_object_vars($v) as $k => $val) {
            echo "[", bin2hex($k), "=>", var_export($val, true), "]";
        }
    } else {
        echo " val=", var_export($v, true);
        if (is_float($v)) {
            echo " hex=", bin2hex(pack('d', $v));
        }
    }
    echo " err=", json_last_error(), " ", json_last_error_msg(), "\n";
}

$nums = [
    '9223372036854775807',
    '9223372036854775808',
    '9223372036854775809',
    '-9223372036854775808',
    '-9223372036854775809',
    '9223372036854775806',
    '1e2',
    '1.0',
    '-0',
    '-0.0',
    '9007199254740993',
    '1e309',
    '1e-400',
];
foreach ($nums as $n) {
    show("num $n", json_decode($n));
    show("big $n", json_decode($n, true, 512, JSON_BIGINT_AS_STRING));
}

$keys = [
    '{"1":"a","1":"b"}',
    '{"01":"a","1":"b"}',
    '{"9223372036854775807":"A","9223372036854775808":"B"}',
    '{"-9223372036854775808":"A"}',
    '{"0":"z","1":"y"}',
    '{"1e2":"x"}',
    '{"\u0000a":1}',
    '{"\u0000":1}',
    '{"":1,"":2}',
];
foreach ($keys as $j) {
    show("obj $j", json_decode($j));
    show("arr $j", json_decode($j, true));
}

echo "depth1 [] ";
var_dump(json_decode('[]', true, 1));
echo json_last_error_msg(), "\n";
echo "depth1 1 ";
var_dump(json_decode('1', true, 1));
echo "depth2 [[]] ";
var_dump(json_decode('[[]]', true, 2));
echo json_last_error_msg(), "\n";
echo "depth3 [[]] ";
var_dump(json_decode('[[]]', true, 3) !== null);

$s = "\"a\xFFb\"";
$ign = json_decode($s, true, 512, JSON_INVALID_UTF8_IGNORE);
$sub = json_decode($s, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
echo "ignore=", var_export($ign, true), " hex=", bin2hex((string)$ign), "\n";
echo "subst=", bin2hex((string)$sub), "\n";

echo "unterm ";
var_dump(json_decode('"'));
echo json_last_error(), " ", json_last_error_msg(), "\n";
echo "unterm2 ";
var_dump(json_decode('"123'));
echo json_last_error(), " ", json_last_error_msg(), "\n";
echo "nulstr ";
var_dump(json_decode('"'.chr(0)));
echo json_last_error(), " ", json_last_error_msg(), "\n";

echo "nulprop ";
var_dump(json_decode('{"key":{"\u0000":"aa"}}'));
echo json_last_error(), " ", json_last_error_msg(), "\n";

echo "dup-num-key security ";
$p = json_decode('{"uid":0,"uid":1}', true);
echo "uid=", var_export($p['uid'] ?? null, true), "\n";

echo "probe-done\n";
