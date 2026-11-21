<?php
function line($s) { echo $s, "\n"; }

$doc = Dom\HTMLDocument::createFromString('<!doctype html><body><template id="t">X</template></body>', LIBXML_NOERROR);
$t = $doc->getElementById('t');
line("IAH before");
try {
    $t->insertAdjacentHTML(Dom\AdjacentPosition::BeforeEnd, '<div id="z">Z</div><b id="y">Y</b>');
    line("IAH ok children=" . $t->childNodes->length . " inner=" . $t->innerHTML);
    $z = $doc->getElementById('z');
    line("get z=" . ($z ? $z->nodeName . " parent=" . ($z->parentNode->nodeName ?? "null") : "null"));
    line("save=" . $doc->saveHTML($t));
} catch (Throwable $e) {
    line("IAH ex " . $e->getMessage());
}

line("--- svg xlink ---");
$d = Dom\HTMLDocument::createFromString('<svg><a xlink:href="http://x" id="a">t</a><rect xml:lang="en"/></svg>', LIBXML_NOERROR);
$a = $d->getElementsByTagName('a')[0];
foreach ($a->attributes as $attr) {
    $bytes = bin2hex($attr->nodeName);
    line("attr name={$attr->nodeName} hex={$bytes} ns=" . ($attr->namespaceURI ?? "null") . " val=" . $attr->value);
}
line("svg html=" . $d->documentElement->innerHTML);

line("--- math annotation ---");
$m = Dom\HTMLDocument::createFromString('<math><annotation-xml encoding="text/html"><div onclick="x"><img alt="</math><img src=x onerror=alert(1)>"></div></annotation-xml></math>', LIBXML_NOERROR);
line("math=" . $m->saveHTML());
line("imgs=" . $m->getElementsByTagName('img')->length);

line("--- table frag ---");
$tb = Dom\HTMLDocument::createFromString('<table><tr id="r"></tr></table>', LIBXML_NOERROR);
$r = $tb->getElementById('r');
$r->innerHTML = '<td>cell</td>';
line("tr inner=" . $r->innerHTML . " child=" . $r->firstChild?->nodeName);

line("--- select ---");
$sdoc = Dom\HTMLDocument::createFromString('<select id="s"></select>', LIBXML_NOERROR);
$s = $sdoc->getElementById('s');
$s->innerHTML = '<option>a</option><div>no</div>';
line("select=" . $s->innerHTML);

line("--- clone deep ---");
$c = Dom\HTMLDocument::createFromString('<template id="t"><b>hi</b></template>', LIBXML_NOERROR);
$ct = $c->getElementById('t');
$cl = $ct->cloneNode(true);
line("clone inner=" . $cl->innerHTML);
$imp = $c->importNode($ct, true);
line("import inner=" . $imp->innerHTML);

line("--- noscript ---");
$n = Dom\HTMLDocument::createFromString('<noscript><img src=x onerror=alert(1)></noscript>', LIBXML_NOERROR);
line("noscript=" . $n->body->innerHTML);
line("imgs=" . $n->getElementsByTagName('img')->length);

line("--- comment ---");
$cm = Dom\HTMLDocument::createEmpty();
$body = $cm->createElement('body');
$cm->appendChild($body);
$comment = $cm->createComment('--><img src=x onerror=alert(1)><!--');
$body->appendChild($comment);
line("comment ser=" . $body->innerHTML);
$body->innerHTML = $body->innerHTML;
line("comment reparse=" . $body->innerHTML . " imgs=" . $body->getElementsByTagName('img')->length);

line("--- style breakout ---");
$st = Dom\HTMLDocument::createFromString('<style></style>', LIBXML_NOERROR);
$style = $st->getElementsByTagName('style')[0];
$style->textContent = '</style><img src=x onerror=alert(1)>';
line("style ser=" . $st->saveHTML($style));
$st2 = Dom\HTMLDocument::createFromString($st->saveHTML(), LIBXML_NOERROR);
line("style reparse imgs=" . $st2->getElementsByTagName('img')->length);

line("--- foreignObject ---");
$fo = Dom\HTMLDocument::createFromString('<svg><foreignObject id="f"></foreignObject></svg>', LIBXML_NOERROR);
$f = $fo->getElementById('f');
line("fo tag=" . ($f->nodeName ?? "missing") . " ns=" . ($f->namespaceURI ?? "null"));
if ($f) {
    $f->innerHTML = '<div id="d">x</div>';
    line("fo inner=" . $f->innerHTML);
    $div = $fo->getElementById('d');
    line("div ns=" . ($div->namespaceURI ?? "null") . " name=" . ($div->nodeName ?? "null"));
}

line("--- plaintext ---");
$p = Dom\HTMLDocument::createFromString('<div><plaintext><img src=x>', LIBXML_NOERROR);
line("plain=" . $p->saveHTML());

line("DONE");
