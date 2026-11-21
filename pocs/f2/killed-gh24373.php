<?php
echo "PHP ".PHP_VERSION."\n";
class D {
    public function __destruct() {
        $GLOBALS['r'] = unserialize('a:2:{i:0;O:8:"stdClass":0:{}i:1;r:2;}');
    }
}
unserialize('a:2:{i:0;O:1:"D":0:{}i:0;i:1;}');
var_dump($GLOBALS['r'][0] === $GLOBALS['r'][1]);
echo "SURVIVED\n";
