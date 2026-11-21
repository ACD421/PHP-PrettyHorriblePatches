<?php
[$sock, $script] = [$argv[1], $argv[2]];
$rec = fn(int $t, string $b) => pack('CCnnCx', 1, $t, 1, strlen($b), 0) . $b;
$kv = function (string $k, string $v): string {
    $l = fn(int $n) => $n < 128 ? chr($n) : pack('N', $n | 0x80000000);
    return $l(strlen($k)) . $l(strlen($v)) . $k . $v;
};
$p = $kv('SCRIPT_FILENAME', $script) . $kv('REQUEST_METHOD', 'GET') . $kv('SCRIPT_NAME', '/t.php')
   . $kv('SERVER_PROTOCOL', 'HTTP/1.1') . $kv('REQUEST_URI', '/t.php') . $kv('QUERY_STRING', '');
$s = stream_socket_client("unix://$sock", $en, $es, 10);
if (!$s) {
    fwrite(STDERR, "connect failed $en $es\n");
    exit(1);
}
fwrite($s, $rec(1, pack('nCx5', 1, 0)) . $rec(4, $p) . $rec(4, '') . $rec(5, ''));
$out = '';
while (!feof($s)) {
    $h = stream_get_contents($s, 8);
    if (strlen($h) < 8) break;
    $d = unpack('Cv/Ct/nid/nlen/Cpad', $h);
    $body = ($d['len'] + $d['pad']) ? stream_get_contents($s, $d['len'] + $d['pad']) : '';
    if ($d['t'] === 6 || $d['t'] === 7) $out .= substr($body, 0, $d['len']);
    if ($d['t'] === 3) break;
}
echo preg_replace('~^.*?\r\n\r\n~s', '', $out);
