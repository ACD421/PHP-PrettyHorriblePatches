<?php
// cloneNode(true) and cross-document importNode(true) must copy template contents.
echo "php=", PHP_VERSION, "\n";
$dom = Dom\HTMLDocument::createFromString(
    '<!doctype html><template id="t"><b id="inner">hello</b><template><i>nested</i></template></template>',
    LIBXML_NOERROR
);
$tpl = $dom->getElementById('t');
if (!$tpl) {
    fwrite(STDERR, "no template\n");
    exit(2);
}
echo "src=", $tpl->innerHTML, "\n";

$clone = $tpl->cloneNode(true);
echo "clone_inner=", $clone->innerHTML, "\n";
echo "clone_save=", $dom->saveHTML($clone), "\n";
echo "src_after_clone=", $tpl->innerHTML, "\n";

$other = Dom\HTMLDocument::createEmpty();
$imported = $other->importNode($tpl, true);
echo "import_same=", ($imported === $tpl) ? "yes" : "no", "\n";
echo "import_inner=", $imported->innerHTML, "\n";
echo "import_save=", $other->saveHTML($imported), "\n";
echo "src_after_import=", $tpl->innerHTML, "\n";

$shallow = $tpl->cloneNode(false);
echo "shallow_inner=", $shallow->innerHTML, "\n";
echo "done\n";
