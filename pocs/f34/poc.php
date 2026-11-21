<?php
// Overlong UTF-8 (2-byte form of ASCII) percent-encoded in a WHATWG host.
// URL Standard UTF-8 decode must reject these (U+FFFD). Lexbor's
// lxb_encoding_decode_valid_utf_8_single() accepts them, and IDNA then
// emits the corresponding ASCII host.

echo "php=" . PHP_VERSION . "\n";

if (!class_exists('Uri\\WhatWg\\Url')) {
    echo "NO_URI_EXT\n";
    exit(0);
}

function show(string $label, string $raw, string $expect): void {
    echo "---- $label ----\n";
    echo "raw=$raw\n";
    echo "raw_contains_expect=" . (str_contains($raw, $expect) ? "yes" : "no") . "\n";
    $legacy = parse_url($raw, PHP_URL_HOST);
    echo "parse_url_host=" . var_export($legacy, true) . "\n";
    $url = Uri\WhatWg\Url::parse($raw);
    if ($url === null) {
        echo "whatwg=null\n";
        return;
    }
    $host = $url->getAsciiHost();
    echo "whatwg_host=" . var_export($host, true) . "\n";
    if (method_exists($url, 'getHostType')) {
        $type = $url->getHostType();
        echo "whatwg_type=" . ($type === null ? "null" : $type->name) . "\n";
    }
    echo "whatwg_url=" . $url->toAsciiString() . "\n";
    echo "host_is_expect=" . ($host === $expect ? "yes" : "no") . "\n";
}

// overlong e v i l . c o m  (C1 A5 = U+0065, etc.)
show(
    "overlong-evil",
    "http://%C1%A5%C1%B6%C1%A9%C1%AC.com/",
    "evil.com"
);

// overlong 1 2 7 . 0 . 0 . 1  (C0 B1 = U+0031, C0 AE = U+002E, ...)
show(
    "overlong-loopback",
    "http://%C0%B1%C0%B2%C0%B7%C0%AE%C0%B0%C0%AE%C0%B0%C0%AE%C0%B1/",
    "127.0.0.1"
);

// invalid continuation: C0 65 decodes to U+0065 because continuation bytes are not checked
show(
    "bad-continuation",
    "http://%C0%65%C0%76%C0%69%C0%6C.com/",
    "evil.com"
);
