<?php
// adoptNode of an HTML <template> must keep the template contents.
// The side fragment is keyed on the source document and is not moved.
echo "php=", PHP_VERSION, "\n";
$old = Dom\HTMLDocument::createFromString(
    '<!doctype html><template id="t"><b id="inner">hello</b><template><i id="n">nested</i></template></template>',
    LIBXML_NOERROR
);
$tpl = $old->getElementById('t');
if (!$tpl) {
    fwrite(STDERR, "no template\n");
    exit(2);
}
echo "before=", $tpl->innerHTML, "\n";

$new = Dom\HTMLDocument::createEmpty();
$adopted = $new->adoptNode($tpl);
$new->appendChild($adopted);
echo "after=", $adopted->innerHTML, "\n";
echo "save=", $new->saveHTML($adopted), "\n";
echo "doc_has_hello=", str_contains($new->saveHTML(), "hello") ? "yes" : "no", "\n";
echo "ns=", $adopted->namespaceURI, "\n";
if (method_exists($old, 'debugGetTemplateCount')) {
    echo "old_count=", $old->debugGetTemplateCount(), " new_count=", $new->debugGetTemplateCount(), "\n";
}
$wr = WeakReference::create($adopted);
unset($tpl, $adopted);
gc_collect_cycles();
echo "weak_after_unset=", ($wr->get() === null ? "freed" : "alive"), "\n";
if (method_exists($old, 'debugGetTemplateCount')) {
    echo "old_count_after_tpl_free=", $old->debugGetTemplateCount(), "\n";
}
unset($old, $new);
gc_collect_cycles();
echo "survived\n";
