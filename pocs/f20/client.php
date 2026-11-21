<?php
function fcgi_len($n) {
    if ($n < 128) return chr($n);
    return chr((($n >> 24) & 0x7f) | 0x80) . chr(($n >> 16) & 0xff) . chr(($n >> 8) & 0xff) . chr($n & 0xff);
}
function fcgi_pair($k, $v) { return fcgi_len(strlen($k)) . fcgi_len(strlen($v)) . $k . $v; }
function fcgi_rec($type, $id, $body) {
    $pad = (8 - (strlen($body) % 8)) % 8;
    return pack('CCnnCC', 1, $type, $id, strlen($body), $pad, 0) . $body . str_repeat("\0", $pad);
}
function fcgi_send($port, $params, $body = '') {
    $pkt = fcgi_rec(1, 1, "\x00\x01\x00\x00\x00\x00\x00\x00");
    $pkt .= fcgi_rec(4, 1, $params);
    $pkt .= fcgi_rec(4, 1, '');
    $pkt .= fcgi_rec(5, 1, $body);
    $fp = fsockopen('127.0.0.1', $port, $e, $es, 2);
    if (!$fp) { fwrite(STDERR, "connect $e $es\n"); return ''; }
    stream_set_timeout($fp, 3);
    fwrite($fp, $pkt);
    $out = '';
    $start = microtime(true);
    while (!feof($fp) && (microtime(true) - $start) < 2.5) {
        $c = fread($fp, 8192);
        if ($c === false || $c === '') break;
        $out .= $c;
    }
    fclose($fp);
    $stdout = '';
    $off = 0; $len = strlen($out);
    while ($off + 8 <= $len) {
        $type = ord($out[$off + 1]);
        $clen = (ord($out[$off + 4]) << 8) | ord($out[$off + 5]);
        $pad = ord($out[$off + 6]);
        $off += 8;
        if ($off + $clen + $pad > $len) break;
        $b = substr($out, $off, $clen);
        $off += $clen + $pad;
        if ($type === 6) $stdout .= $b;
        if ($type === 3) break;
    }
    return $stdout;
}

$script = $argv[1];
$user = '<script>alert(1)</script>';
$auth = 'Basic ' . base64_encode($user . ':x');
$slow = '';
$slow .= fcgi_pair('REQUEST_METHOD', 'GET');
$slow .= fcgi_pair('SCRIPT_FILENAME', $script);
$slow .= fcgi_pair('SCRIPT_NAME', '/sleep.php');
$slow .= fcgi_pair('QUERY_STRING', 'sleep=3');
$slow .= fcgi_pair('REQUEST_URI', '/sleep.php?sleep=3');
$slow .= fcgi_pair('HTTP_AUTHORIZATION', $auth);
$slow .= fcgi_pair('GATEWAY_INTERFACE', 'CGI/1.1');

$st = '';
$st .= fcgi_pair('REQUEST_METHOD', 'GET');
$st .= fcgi_pair('SCRIPT_FILENAME', $script);
$st .= fcgi_pair('SCRIPT_NAME', '/status');
$st .= fcgi_pair('QUERY_STRING', 'html&full');
$st .= fcgi_pair('REQUEST_URI', '/status?html&full');
$st .= fcgi_pair('GATEWAY_INTERFACE', 'CGI/1.1');

$fp = fsockopen('127.0.0.1', 9000, $e, $es, 2);
if (!$fp) { fwrite(STDERR, "slow connect fail\n"); exit(1); }
stream_set_blocking($fp, false);
$pkt = fcgi_rec(1, 1, "\x00\x01\x00\x00\x00\x00\x00\x00");
$pkt .= fcgi_rec(4, 1, $slow);
$pkt .= fcgi_rec(4, 1, '');
$pkt .= fcgi_rec(5, 1, '');
fwrite($fp, $pkt);
usleep(400000);
$status = fcgi_send(9000, $st);
echo "STATUS_BEGIN\n";
echo $status;
echo "STATUS_END\n";
fclose($fp);
