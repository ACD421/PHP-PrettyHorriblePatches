<?php
fwrite(STDERR, "PHP " . PHP_VERSION . "\n");
$base = sys_get_temp_dir() . '/phar-zipslip-' . PHP_VERSION . '-' . getmypid();
@mkdir($base, 0777, true);
$sentinel = dirname($base) . '/ZIPSLIP-SENTINEL-' . getmypid();
@unlink($sentinel);

function build_zip($entries) {
    $locals = '';
    $central = '';
    $offset = 0;
    $n = 0;
    foreach ($entries as $name => $data) {
        $crc = crc32($data);
        $nlen = strlen($name);
        $dlen = strlen($data);
        $blob = "PK\x03\x04"
            . pack('v', 20) . pack('v', 0) . pack('v', 0)
            . pack('v', 0) . pack('v', 0)
            . pack('V', $crc) . pack('V', $dlen) . pack('V', $dlen)
            . pack('v', $nlen) . pack('v', 0)
            . $name . $data;
        $locals .= $blob;
        $central .= "PK\x01\x02"
            . pack('v', 20) . pack('v', 20) . pack('v', 0) . pack('v', 0)
            . pack('v', 0) . pack('v', 0)
            . pack('V', $crc) . pack('V', $dlen) . pack('V', $dlen)
            . pack('v', $nlen) . pack('v', 0) . pack('v', 0)
            . pack('v', 0) . pack('v', 0) . pack('V', 0)
            . pack('V', $offset)
            . $name;
        $offset += strlen($blob);
        $n++;
    }
    $eocd = "PK\x05\x06" . pack('vvvvVV', 0, 0, $n, $n, strlen($central), $offset) . pack('v', 0);
    return $locals . $central . $eocd;
}

$names = [
    '../outside.txt' => 'SLIP1',
    '../../outside2.txt' => 'SLIP2',
    'foo/../../outside3.txt' => 'SLIP3',
    '/tmp/abs.txt' => 'ABS',
    '..' => 'DOTDOT',
    'foo/..' => 'FOODOT',
    'foo/../../../etc/passwd' => 'DEEP',
    'ok.txt' => 'INSIDE',
];
$zipPath = $base . '/evil.zip';
file_put_contents($zipPath, build_zip($names));
$dest = $base . '/out';
@mkdir($dest, 0777, true);
try {
    $p = new PharData($zipPath);
    fwrite(STDERR, "OPENED\n");
    foreach ($p as $entry) {
        fwrite(STDERR, "NAME " . $entry->getFilename() . " PATH " . $entry . "\n");
    }
    $p->extractTo($dest);
    fwrite(STDERR, "EXTRACT=ok\n");
} catch (Throwable $e) {
    fwrite(STDERR, "EX " . get_class($e) . ": " . $e->getMessage() . "\n");
}
fwrite(STDERR, "SENTINEL " . (file_exists($sentinel) ? 'yes' : 'no') . "\n");
if (is_dir($dest)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dest, FilesystemIterator::SKIP_DOTS));
    $any = false;
    foreach ($it as $f) {
        $any = true;
        fwrite(STDERR, "TREE " . substr($f->getPathname(), strlen($dest)) . "\n");
    }
    if (!$any) fwrite(STDERR, "TREE empty\n");
}
$parent = dirname($dest);
fwrite(STDERR, "PARENT_LIST\n");
foreach (scandir($base) as $n) {
    fwrite(STDERR, "  $n\n");
}
fflush(STDERR);
