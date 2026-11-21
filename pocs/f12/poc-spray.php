<?php
class Bar implements JsonSerializable {
    public function jsonSerialize(): mixed {
        global $ref, $spray;
        $ref = null;
        $spray = [];
        for ($i = 0; $i < 4000; $i++) {
            $o = new stdClass;
            $o->n = $i;
            $o->s = str_repeat('A', 24);
            $spray[] = $o;
        }
        return ['k' => 1];
    }
}

$arr = [new Bar];
$ref = &$arr[0];
$out = json_encode($arr);
$bad = 0;
$shown = 0;
foreach ($spray as $i => $o) {
    $ok = is_object($o) && ($o->n ?? null) === $i && ($o->s ?? '') === str_repeat('A', 24);
    if (!$ok) {
        $bad++;
        if ($shown < 5) {
            echo "corrupt i=$i type=", gettype($o), "\n";
            $shown++;
        }
    }
}
echo "encoded=", var_export($out, true), " bad=$bad/", count($spray), "\n";
echo "spray-done\n";
