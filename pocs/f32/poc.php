<?php
/**
 * FILTER_VALIDATE_URL host confusion.
 * Prints filter_var next to parse_url, then the host a WHATWG client and curl use.
 * Curl targets are integer forms of 127.0.0.1 on port 1 only.
 */
error_reporting(E_ALL & ~E_DEPRECATED);

echo "PHP_VERSION=" . PHP_VERSION . "\n";

function dump_case(string $label, string $url, bool $curl): void {
    $filtered = filter_var($url, FILTER_VALIDATE_URL);
    $parts = parse_url($url);
    $host = is_array($parts) ? ($parts['host'] ?? null) : null;
    $scheme = is_array($parts) ? ($parts['scheme'] ?? null) : null;
    $as_ip = is_string($host) ? filter_var($host, FILTER_VALIDATE_IP) : false;

    echo "---- {$label} ----\n";
    echo "filter_var=" . json_encode($filtered, JSON_UNESCAPED_SLASHES) . "\n";
    echo "parse_url=" . json_encode($parts, JSON_UNESCAPED_SLASHES) . "\n";
    echo "parse_host=" . json_encode($host, JSON_UNESCAPED_SLASHES)
        . " parse_scheme=" . json_encode($scheme)
        . " filter_validate_ip=" . json_encode($as_ip, JSON_UNESCAPED_SLASHES) . "\n";

    $whatwg_host = null;
    if (class_exists('Uri\\WhatWg\\Url')) {
        $errors = null;
        $w = Uri\WhatWg\Url::parse($url, null, $errors);
        if ($w === null) {
            echo "whatwg_host=null\n";
        } else {
            $whatwg_host = $w->getAsciiHost();
            echo "whatwg_scheme=" . json_encode($w->getScheme())
                . " whatwg_host=" . json_encode($whatwg_host, JSON_UNESCAPED_SLASHES)
                . " whatwg_path=" . json_encode($w->getPath(), JSON_UNESCAPED_SLASHES)
                . " whatwg_url=" . json_encode($w->toAsciiString(), JSON_UNESCAPED_SLASHES) . "\n";
        }
    } else {
        echo "whatwg_host=unavailable\n";
    }

    $curl_target = null;
    if ($curl && function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_CONNECTTIMEOUT_MS => 800,
            CURLOPT_TIMEOUT_MS => 1200,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ]);
        curl_exec($ch);
        $curl_target = curl_error($ch);
        $errno = curl_errno($ch);
        $primary = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        echo "curl_errno={$errno} curl_primary_ip=" . json_encode($primary)
            . " curl_error=" . json_encode($curl_target) . "\n";
        if (is_string($primary) && $primary !== '' && $primary !== '127.0.0.1') {
            echo "ABORT non-loopback primary_ip\n";
            exit(2);
        }
    }

    $mismatch = false;
    if ($filtered !== false && is_string($host)) {
        if (is_string($whatwg_host) && strcasecmp($host, $whatwg_host) !== 0) {
            $mismatch = true;
        }
        if (is_string($curl_target) && str_contains($curl_target, '127.0.0.1') && !str_contains($host, '127.0.0.1')) {
            $mismatch = true;
        }
    }
    echo $mismatch ? "BUG host_mismatch=1\n" : "host_mismatch=0\n";
}

dump_case('decimal-integer', 'http://2130706433/', false);
dump_case('decimal-integer-port1', 'http://2130706433:1/', true);
dump_case('compressed-ipv4', 'http://127.1/', false);
dump_case('compressed-ipv4-port1', 'http://127.1:1/', true);
dump_case('hex-ipv4', 'http://0x7f000001/', false);
dump_case('hex-ipv4-port1', 'http://0x7f000001:1/', true);
dump_case('hex-dotted', 'http://0x7f.1/', false);
dump_case('octal-dotted', 'http://0177.0.0.1/', false);
dump_case('two-part', 'https://192.168/', false);

dump_case('backslash-ws', 'ws://127.0.0.1\\foo/', false);
dump_case('backslash-wss', 'wss://127.0.0.1\\foo/', false);
dump_case('backslash-ftp', 'ftp://127.0.0.1\\foo/', false);
dump_case('backslash-http-rejected', 'http://127.0.0.1\\foo/', false);
dump_case('backslash-userinfo-rejected', 'http://evil.example\\@127.0.0.1/', false);
dump_case('tab-rejected', "http://exampl\te.com/", false);

echo "DONE\n";
