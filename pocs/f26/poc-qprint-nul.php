<?php
/**
 * quoted_printable_decode() was taught to keep scanning past NUL.
 * php_quot_print_decode(), used by iconv_mime_decode() for Q encoded-words,
 * still stops at the first NUL even though it is given an explicit length.
 */
if (!extension_loaded('iconv')) {
    fwrite(STDERR, "iconv extension missing\n");
    exit(2);
}

$payload = "abc\0def";
$word = "=?UTF-8?Q?" . $payload . "?=";

$qp = quoted_printable_decode($payload);
$iconv = iconv_mime_decode($word, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
$mb = function_exists('mb_decode_mimeheader') ? mb_decode_mimeheader($word) : null;

echo "qp    ", bin2hex($qp), "\n";
echo "iconv ", bin2hex((string) $iconv), "\n";
if ($mb !== null) {
    echo "mb    ", bin2hex($mb), "\n";
}

$stream = fopen('php://temp', 'w+');
fwrite($stream, $payload);
rewind($stream);
stream_filter_append($stream, 'convert.quoted-printable-decode', STREAM_FILTER_READ);
$filt = stream_get_contents($stream);
fclose($stream);
echo "filt  ", bin2hex($filt), "\n";

if (bin2hex($qp) === '61626300646566' && bin2hex((string) $iconv) === '616263' && str_starts_with(bin2hex((string) $iconv), '616263') && !str_contains(bin2hex((string) $iconv), '646566')) {
    echo "BUG iconv Q decoder dropped bytes after NUL\n";
} else {
    echo "NOBUG\n";
    var_dump($iconv);
}
