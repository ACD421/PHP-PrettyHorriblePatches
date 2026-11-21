<?php
/**
 * curl_reset() clears libcurl options and CURLOPT_PRIVATE, but the PHP-side
 * multipart postfields zval is left set. curl_copy_handle()/clone rebuilds
 * CURLOPT_MIMEPOST from that zval, so the body curl_reset() dropped is sent
 * again.
 */
if (!extension_loaded('curl')) {
    fwrite(STDERR, "curl extension missing\n");
    exit(2);
}

$srv = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if (!$srv) {
    fwrite(STDERR, "bind failed: $errstr\n");
    exit(2);
}
$addr = stream_socket_get_name($srv, false);
stream_set_blocking($srv, false);
$url = "http://$addr/collect";

function drive(mixed $srv, CurlMultiHandle $mh, int $seconds = 3): string
{
    $buf = '';
    $client = null;
    $active = 0;
    $deadline = microtime(true) + $seconds;
    do {
        $mrc = curl_multi_exec($mh, $active);
        if ($mrc !== CURLM_OK && $mrc !== CURLM_CALL_MULTI_PERFORM) {
            echo "multi_exec=$mrc\n";
            break;
        }
        if (!is_resource($client)) {
            $accepted = @stream_socket_accept($srv, 0);
            if (is_resource($accepted)) {
                $client = $accepted;
                stream_set_blocking($client, false);
            }
        } else {
            $chunk = fread($client, 16384);
            if (is_string($chunk) && $chunk !== '') {
                $buf .= $chunk;
            }
            $hdrEnd = strpos($buf, "\r\n\r\n");
            $complete = false;
            if ($hdrEnd !== false) {
                $headers = substr($buf, 0, $hdrEnd);
                $body = substr($buf, $hdrEnd + 4);
                if (preg_match('/Content-Length:\s*(\d+)/i', $headers, $m)) {
                    $complete = strlen($body) >= (int) $m[1];
                } else {
                    $complete = true;
                }
            }
            if ($complete) {
                fwrite($client, "HTTP/1.1 200 OK\r\nContent-Length: 2\r\nConnection: close\r\n\r\nOK");
                fclose($client);
                $client = null;
                if (!$active) {
                    break;
                }
            }
        }
        if ($active) {
            curl_multi_select($mh, 0.05);
        }
    } while (microtime(true) < $deadline);
    if ($client) {
        fclose($client);
    }
    return $buf;
}

function base_handle(string $url): CurlHandle
{
    $ch = curl_init($url);
    // Only CURLFile / CURLStringFile are kept on the PHP handle across
    // curl_easy_reset(). curl_copy_handle() rebuilds MIMEPOST from that zval.
    curl_setopt_array($ch, [
        CURLOPT_POSTFIELDS => [
            'note' => new CURLStringFile('TOKEN123', 'note.txt', 'text/plain'),
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 2,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_HTTPHEADER => ['Expect:'],
    ]);
    return $ch;
}

function report(string $label, string $raw): void
{
    $leaked = str_contains($raw, 'TOKEN123');
    echo "CASE $label ", ($leaked ? 'LEAKED' : 'clean'), "\n";
    $hdrEnd = strpos($raw, "\r\n\r\n");
    if ($raw === '') {
        echo "(no request)\n";
        return;
    }
    echo substr($raw, 0, $hdrEnd === false ? 400 : $hdrEnd), "\n";
    if ($hdrEnd !== false) {
        echo "BODY ", bin2hex(substr($raw, $hdrEnd + 4, 180)), "\n";
    }
}

echo "addr $addr\n";

$ch = base_handle($url);
curl_reset($ch);
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 2,
    CURLOPT_CONNECTTIMEOUT => 2,
    CURLOPT_HTTPHEADER => ['Expect:'],
]);
$mh = curl_multi_init();
curl_multi_add_handle($mh, $ch);
report('reset_original', drive($srv, $mh));
curl_multi_remove_handle($mh, $ch);
curl_multi_close($mh);
curl_close($ch);

$ch = base_handle($url);
curl_reset($ch);
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 2,
    CURLOPT_CONNECTTIMEOUT => 2,
    CURLOPT_HTTPHEADER => ['Expect:'],
]);
$copy = curl_copy_handle($ch);
curl_setopt($copy, CURLOPT_HTTPHEADER, ['Expect:']);
$mh = curl_multi_init();
curl_multi_add_handle($mh, $copy);
report('reset_then_copy', drive($srv, $mh));
curl_multi_remove_handle($mh, $copy);
curl_multi_close($mh);

$clone = clone $ch;
curl_setopt($clone, CURLOPT_HTTPHEADER, ['Expect:']);
$mh = curl_multi_init();
curl_multi_add_handle($mh, $clone);
report('reset_then_clone', drive($srv, $mh));
curl_multi_remove_handle($mh, $clone);
curl_multi_close($mh);

curl_close($ch);
curl_close($copy);
unset($clone);
fclose($srv);
echo "done\n";
