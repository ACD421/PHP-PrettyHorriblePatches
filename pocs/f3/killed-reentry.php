<?php
echo "PHP ".PHP_VERSION."\n";
class D {
    public $x;
    function __destruct() {
        $GLOBALS['inner'] = unserialize('a:2:{i:0;O:8:"stdClass":0:{}i:1;r:2;}');
    }
}
unserialize('a:2:{i:0;O:1:"D":1:{s:1:"x";N;}i:0;i:1;}');
$ok = is_array($GLOBALS['inner']) && $GLOBALS['inner'][0] === $GLOBALS['inner'][1];
echo $ok ? "REENTRY_OK\n" : "REENTRY_BAD\n";
echo "SURVIVED\n";
