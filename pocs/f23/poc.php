<?php
// Lazy proxy subclass adds a property hook. Inheritance gives that property a
// new slot (use_child_prop). The real instance is the parent, so
// zend_lazy_object_init_proxy() clears only the parent's slots. The child's
// slot keeps the value written during the factory, and the get hook's backing
// read returns it instead of the real instance. The slot is also invisible to
// zend_lazy_object_get_gc(), so the proxy<->value cycle is not collected.

class Real {
    public $secret;
    public $other = 'OTHER-REAL';
    function __construct() {
        $this->secret = new stdClass();
        $this->secret->tag = 'FROM-REAL';
    }
}

class Proxy extends Real {
    public $secret {
        get => $this->secret;
    }
}

class Cycle {
    public $back;
    public $tag = 'FROM-PROXY-SHELL';
    function __construct($back) { $this->back = $back; }
    function __destruct() { echo "cycle-dtor\n"; }
}

$rc = new ReflectionClass(Proxy::class);
$proxy = $rc->newLazyProxy(function ($proxy) {
    $proxy->secret = new Cycle($proxy);
    return new Real();
});

echo "other=";
var_dump($proxy->other);

$got = $proxy->secret;
echo "secret-class=" . (is_object($got) ? get_class($got) : gettype($got)) . "\n";
echo "secret-tag=";
var_dump(is_object($got) && isset($got->tag) ? $got->tag : $got);

unset($proxy, $got);
echo "gc=" . gc_collect_cycles() . "\n";
echo "after-gc\n";
