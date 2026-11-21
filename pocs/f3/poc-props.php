<?php
/* object_properties_load() on 8.5/8.4 copies members into typed slots
 * with no zend_verify_property_type(). ArrayObject::__unserialize uses it.
 */
echo "PHP ".PHP_VERSION."\n";
class Foo extends ArrayObject {
    public array $a;
    public int $b;
}
$payload = 'O:3:"Foo":4:{i:0;i:0;i:1;a:0:{}i:2;a:2:{s:1:"a";O:8:"stdClass":0:{}s:1:"b";s:3:"zzz";}i:3;N;}';
try {
    $o = unserialize($payload);
} catch (Throwable $e) {
    echo "CAUGHT ".$e::class.": ".$e->getMessage()."\n";
    exit(0);
}
if (!is_object($o)) {
    echo "NOT_OBJECT ".gettype($o)."\n";
    exit(0);
}
echo "a=".gettype($o->a)." b_type=".gettype($o->b)."\n";
if (is_object($o->a)) {
    echo "A_CLASS=".get_class($o->a)."\n";
}
if (is_string($o->b)) {
    echo "B_STR=".$o->b."\n";
}
echo "DONE\n";
