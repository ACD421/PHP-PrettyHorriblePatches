<?php
/* Array ASSIGN_DIM with a null key under the tracing JIT.
 * zend_jit_fetch_dim_w_helper() frees the OP_DATA TMP when the null-offset
 * deprecation changes the array refcount, then zend_jit_assign_dim_fetch_w_helper()
 * frees that same TMP again. */
class D {
    public function __destruct() {
        echo "dtor\n";
    }
}

function boom(object $box): void {
    $box->a = [1];
    $box->a[null] = new D();
}

$box = new stdClass();
$box->a = [1];

set_error_handler(static function (int $no, string $msg) use ($box): bool {
    if (strpos($msg, 'null as an array offset') !== false) {
        $box->a = null;
    }
    return true;
});

for ($i = 0; $i < 30; $i++) {
    boom($box);
}
echo "survived\n";
