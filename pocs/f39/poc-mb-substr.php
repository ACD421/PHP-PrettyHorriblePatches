<?php
if (!extension_loaded('mbstring')) {
    fwrite(STDERR, "mbstring missing\n");
    exit(2);
}

function ref_substr(string $s, int $from, ?int $len): string
{
    $cps = [];
    $i = 0;
    $n = strlen($s);
    while ($i < $n) {
        $b = ord($s[$i]);
        if ($b < 0x80) {
            $w = 1;
        } elseif (($b & 0xE0) === 0xC0) {
            $w = 2;
        } elseif (($b & 0xF0) === 0xE0) {
            $w = 3;
        } else {
            $w = 4;
        }
        if ($i + $w > $n) {
            break;
        }
        $cps[] = substr($s, $i, $w);
        $i += $w;
    }
    $count = count($cps);
    if ($from < 0) {
        $from = max(0, $count + $from);
    }
    if ($from >= $count) {
        return '';
    }
    if ($len === null) {
        $end = $count;
    } elseif ($len < 0) {
        $end = max($from, $count + $len);
    } else {
        $end = min($count, $from + $len);
    }
    return implode('', array_slice($cps, $from, $end - $from));
}

$emoji = "\u{1F389}";
$parts = [
    str_repeat('a', 255) . $emoji . str_repeat('b', 80),
    str_repeat($emoji, 80) . str_repeat('é', 40),
    str_repeat('é', 300),
    str_repeat('a', 600),
    $emoji . str_repeat('x', 10),
    str_repeat('y', 511) . $emoji . $emoji . 'Z',
];
$bad = 0;
foreach ($parts as $pi => $s) {
    $chars = preg_match_all('/./u', $s);
    $limits = [-3, -1, 0, 1, 2, 254, 255, 256, 257, 300, 400, $chars - 1, $chars, $chars + 2];
    foreach ($limits as $from) {
        foreach ([null, 0, 1, 2, 5, 50, -1, -4] as $len) {
            $expect = ref_substr($s, $from, $len);
            $got = $len === null ? mb_substr($s, $from, null, 'UTF-8') : mb_substr($s, $from, $len, 'UTF-8');
            if ($got !== $expect) {
                $bad++;
                echo "MISMATCH part=$pi from=$from len=", var_export($len, true),
                    " expect=", bin2hex($expect), " got=", bin2hex($got), "\n";
                if ($bad > 12) {
                    echo "too many\n";
                    exit(1);
                }
            }
        }
    }
}
echo $bad === 0 ? "mb_substr matches\n" : "mb_substr mismatches=$bad\n";
