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
        $n = 0;
        while ($b = stream_bucket_make_writeable($in)) {
            $n++;
            echo "BUCKET " . bin2hex($b->data) . "\n";
            $consumed += $b->datalen;
            $b->data .= "X";
            stream_bucket_append($out, $b);
        }
        echo "BUCKETS $n\n";
        return PSFS_PASS_ON;
    }
}

stream_filter_register("myf", "F");
$fp = fopen("php://memory", "w+b");
fwrite($fp, "hello");
rewind($fp);
$GLOBALS["fres"] = stream_filter_append($fp, "myf", STREAM_FILTER_READ);
$d = stream_get_contents($fp);
echo "DATA " . var_export($d, true) . "\n";
