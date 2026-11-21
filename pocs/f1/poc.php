<?php
echo "PHP " . PHP_VERSION . "\n";

class F extends php_user_filter {
    function filter($in, $out, &$consumed, $closing): int {
        echo "FILTER closing=" . (int)$closing . "\n";
        if (!$closing && empty($GLOBALS["did"])) {
            $GLOBALS["did"] = 1;
            echo "REMOVE begin\n";
            $r = @stream_filter_remove($GLOBALS["fres"]);
            echo "REMOVE " . var_export($r, true) . "\n";
        }
        while ($b = stream_bucket_make_writeable($in)) {
            $consumed += $b->datalen;
            $b->data .= "X";
            stream_bucket_append($out, $b);
        }
        return PSFS_PASS_ON;
    }
    function onClose(): void {
        echo "ONCLOSE\n";
    }
}

stream_filter_register("myf", "F");
$fp = fopen("php://memory", "w+b");
fwrite($fp, "hello");
rewind($fp);
$GLOBALS["fres"] = stream_filter_append($fp, "myf", STREAM_FILTER_READ);
echo "READ\n";
$d = @stream_get_contents($fp);
echo "DATA " . var_export($d, true) . "\n";
echo "DONE\n";
