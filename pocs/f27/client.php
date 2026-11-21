<?php
/* Minimal FastCGI responder client. Loopback only. */
function fcgi_len($n) {
    if ($n < 128) {
        return chr($n);
    }
    return chr((($n >> 24) & 0x7f) | 0x80)
        . chr(($n >> 16) & 0xff)
        . chr(($n >> 8) & 0xff)
        . chr($n & 0xff);
}
function fcgi_pair($name, $val) {
    return fcgi_len(strlen($name)) . fcgi_len(strlen($val)) . $name . $val;
}
function fcgi_rec($type, $id, $body) {
    $pad = (8 - (strlen($body) % 8)) % 8;
    $hdr = pack('CCnnCC', 1, $type, $id, strlen($body), $pad, 0);
    return $hdr . $body . str_repeat("\0", $pad);
}

$host = $argv[1] ?? '127.0.0.1';
$port = (int)($argv[2] ?? 19085);
$script = $argv[3] ?? '';
$suffix_n = (int)($argv[4] ?? 80);
$path_info = $argv[5] ?? '';

if ($host !== '127.0.0.1' || $script === '') {
    fwrite(STDERR, "usage: client.php 127.0.0.1 port script suffix_n [path_info]\n");
    exit(2);
}

$sf = $script . '/' . str_repeat('A', $suffix_n);
$pi_first = ($argv[6] ?? '') === 'pi_first';
$pairs = [
    ['REQUEST_METHOD', 'GET'],
    ['SCRIPT_FILENAME', $sf],
    ['SCRIPT_NAME', '/index.php/' . str_repeat('A', $suffix_n)],
    ['PATH_INFO', $path_info],
    ['QUERY_STRING', ''],
    ['SERVER_SOFTWARE', 'Apache'],
    ['GATEWAY_INTERFACE', 'CGI/1.1'],
    ['REDIRECT_STATUS', '200'],
    ['SECRET_CANARY', 'CANARY-SECRET-VALUE'],
];
if ($pi_first) {
    $pairs = [
        ['PATH_INFO', $path_info],
        ['REQUEST_METHOD', 'GET'],
        ['SCRIPT_FILENAME', $sf],
        ['SCRIPT_NAME', '/index.php'],
        ['QUERY_STRING', ''],
        ['SECRET_CANARY', 'CANARY-SECRET-VALUE'],
    ];
}
$params = '';
foreach ($pairs as $pair) {
    $params .= fcgi_pair($pair[0], $pair[1]);
}

$pkt = fcgi_rec(1, 1, "\x00\x01\x00\x00\x00\x00\x00\x00");
$pkt .= fcgi_rec(4, 1, $params);
$pkt .= fcgi_rec(4, 1, '');
$pkt .= fcgi_rec(5, 1, '');

$fp = @fsockopen($host, $port, $errno, $errstr, 2.0);
if (!$fp) {
    fwrite(STDERR, "connect fail $errno $errstr\n");
    exit(3);
}
stream_set_timeout($fp, 2);
fwrite($fp, $pkt);
$out = '';
while (!feof($fp)) {
    $chunk = fread($fp, 8192);
    if ($chunk === false || $chunk === '') {
        break;
    }
    $out .= $chunk;
}
fclose($fp);

/* Extract STDOUT bodies */
$stdout = '';
$stderr = '';
$off = 0;
$len = strlen($out);
while ($off + 8 <= $len) {
    $ver = ord($out[$off]);
    $type = ord($out[$off + 1]);
    $clen = (ord($out[$off + 4]) << 8) | ord($out[$off + 5]);
    $pad = ord($out[$off + 6]);
    $off += 8;
    if ($off + $clen + $pad > $len) {
        break;
    }
    $body = substr($out, $off, $clen);
    $off += $clen + $pad;
    if ($type === 6) {
        $stdout .= $body;
    } elseif ($type === 7) {
        $stderr .= $body;
    } elseif ($type === 3) {
        break;
    }
}
echo "STDOUT_BEGIN\n";
echo $stdout;
echo "STDOUT_END\n";
if ($stderr !== '') {
    echo "STDERR_BEGIN\n";
    echo $stderr;
    echo "STDERR_END\n";
}
