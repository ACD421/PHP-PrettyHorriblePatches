<?php
/**
 * GH-21024: json_encode() UAF when jsonSerialize() drops the last reference
 * to the object being encoded. ext/json/json_encoder.c
 * php_json_encode_serializable_object() captures the recursion guard, then
 * zend_call_known_function() without GC_ADDREF. Nulling the alias frees the
 * object; ZEND_GUARD_UNPROTECT_RECURSION writes through the dangling guard.
 */
class Bar implements JsonSerializable {
    public function jsonSerialize(): mixed {
        global $ref;
        $ref = null;
        return ['k' => 1];
    }
}

$arr = [new Bar];
$ref = &$arr[0];
$out = json_encode($arr);
echo "encoded=";
var_dump($out);
echo "survived\n";
