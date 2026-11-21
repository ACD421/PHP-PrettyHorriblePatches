<?php
fwrite(STDOUT, "PHP ".PHP_VERSION."\n");
function go($name, $fn) {
    try {
        $r = $fn();
        $t = gettype($r);
        if (is_string($r)) {
            $t .= " len=".strlen($r);
        } elseif (is_array($r)) {
            $t .= " n=".count($r);
        } elseif (is_bool($r)) {
            $t .= $r ? " true" : " false";
        } elseif (is_int($r) || is_float($r)) {
            $t .= " v=".$r;
        }
        fwrite(STDOUT, "OK $name $t\n");
    } catch (Throwable $e) {
        fwrite(STDOUT, "EX $name ".$e::class." ".$e->getMessage()."\n");
    }
}

go("pack a0", fn() => pack("a0", "ab"));
go("pack Z0", fn() => pack("Z0", "ab"));
go("pack Z1", fn() => pack("Z1", "ab"));
go("pack Z*", fn() => pack("Z*", "ab"));
go("pack h empty", fn() => pack("h", ""));
go("pack H*", fn() => pack("H*", "AB"));
go("pack h0", fn() => pack("h0", "AB"));
go("pack x0", fn() => pack("x0"));
go("pack @5", fn() => pack("@5"));
go("pack X5", fn() => pack("X5"));
go("pack xh", fn() => pack("xh", "a"));
go("pack ch", fn() => pack("ch", 0, "AB"));
go("pack a*c", fn() => pack("a*c", "hi", 65));
go("unpack h*", fn() => unpack("h*", "AB"));
go("unpack A*", fn() => unpack("A*", "ab "));
go("unpack Z*", fn() => unpack("Z*", "a\0b"));
go("unpack X", fn() => unpack("X", "ab"));
go("unpack a*", fn() => unpack("a*", "hi"));
go("unpack c5 short", fn() => unpack("c5", "A"));
go("unpack h2147483647", fn() => unpack("h2147483647", "A"));
go("unpack a2147483648", fn() => unpack("a2147483648name", "hi"));
go("wordwrap w0 nl", fn() => wordwrap("a b c d", 0, "\n", false));
go("wordwrap w-1 br", fn() => wordwrap("a b c d e", -1, "<br>", false));
go("wordwrap many breaks", fn() => wordwrap(str_repeat("\r\n", 30), 75, "\r\n", false));
go("wordwrap cut", fn() => wordwrap("abcdefghij", 3, "--", true));
go("wordwrap w0 cut", fn() => wordwrap("abc", 0, "\n", true));
go("wordwrap INT_MIN", fn() => wordwrap("a b", PHP_INT_MIN, "\n", false));
go("chunk 0", fn() => chunk_split("abc", 0));
go("chunk neg", fn() => chunk_split("abc", -1));
go("chunk big", fn() => chunk_split("abc", PHP_INT_MAX, "Z"));
go("chunk empty end", fn() => chunk_split("abcd", 2, ""));
go("chunk normal", fn() => chunk_split("abcd", 1, "-"));
go("csv plain", fn() => str_getcsv("a,b,\"c\""));
go("csv unterm", fn() => str_getcsv("\"field\",\""));
go("csv nul", fn() => str_getcsv("aaa\0bbb", "\0", "\""));
go("csv only quote", fn() => str_getcsv("\""));
go("sprintf pct", fn() => sprintf("%"));
go("sprintf s", fn() => sprintf("%s", "ab"));
go("sprintf star", fn() => sprintf("%*s", 4, "ab"));
go("sprintf prec", fn() => sprintf("%.2f", 1.239));
go("sprintf missing", fn() => sprintf("%s %s", "a"));
go("sprintf pad", fn() => sprintf("%'+10.2f", -1.5));
go("sprintf g-1", fn() => sprintf("%.1g", 1.25));
go("sscanf set", fn() => sscanf("abc", "%[a-c]"));
go("sscanf excl", fn() => sscanf("xyz", "%[^a]"));
go("sscanf bracket", fn() => sscanf("]a]", "%[]a]"));
go("sscanf bad", fn() => sscanf("abc", "%[abc"));
go("sscanf i", fn() => sscanf("  -12x", "%i"));
go("sscanf width", fn() => sscanf("abcdef", "%3s%3s"));
go("fill 0", fn() => array_fill(0, 0, 1));
go("fill neg", fn() => array_fill(0, -1, 1));
go("fill start max", fn() => array_fill(PHP_INT_MAX, 1, 1));
go("pad min", fn() => array_pad([1], PHP_INT_MIN, 0));
go("pad 0", fn() => array_pad([1, 2], 0, 9));
go("pad neg", fn() => array_pad([1], -3, 7));
go("slice max", fn() => array_slice([1, 2, 3], 0, PHP_INT_MAX));
go("slice min", fn() => array_slice([1, 2, 3], 1, PHP_INT_MIN));
go("slice neg", fn() => array_slice([1, 2, 3], -10, -1));
go("chunk arr 0", fn() => array_chunk([1, 2, 3], 0));
go("range char", fn() => range("z", "a"));
go("range step", fn() => range(10, 1, 3));
go("explode empty", fn() => explode("", "abc"));
go("explode min", fn() => explode("a", "aXaYa", PHP_INT_MIN));
go("explode -1", fn() => explode(",", "a,b,c", -1));
go("implode min", fn() => implode(",", [PHP_INT_MIN, 0]));
go("substr_replace", fn() => substr_replace("abcdef", "X", 2, -2));
go("substr_replace neg", fn() => substr_replace("abcdef", "Z", -2, PHP_INT_MIN));
go("str_replace shrink", fn() => str_replace("a", "", str_repeat("ab", 50)));
go("str_pad neg", fn() => str_pad("a", -1));
go("str_pad empty", fn() => str_pad("a", 10, ""));
go("str_repeat 0", fn() => str_repeat("a", 0));
go("str_repeat neg", fn() => str_repeat("a", -1));
go("hex2bin odd", fn() => hex2bin("abc"));
go("hex2bin bad", fn() => hex2bin("0g"));
go("addcslashes", fn() => addcslashes("abc", "a..z"));
go("nl2br", fn() => nl2br("a\rb\nc\r\n"));
go("hebrev", fn() => hebrev("a b c"));
go("strrev empty", fn() => strrev(""));
go("strrev", fn() => strrev("abc"));
go("strip open", fn() => strip_tags("<br"));
go("strip nest", fn() => strip_tags("<a href='>' >x</a><b>y"));
go("quotemeta", fn() => quotemeta("a.b(c)"));
go("strpos min", fn() => strpos("abc", "b", PHP_INT_MIN));
go("strrpos min", fn() => strrpos("abcabc", "b", PHP_INT_MIN));
go("substr_compare", fn() => substr_compare("abcd", "bc", 1, -1));
go("count_chars", fn() => count_chars("aba", 1));
go("bin2hex", fn() => bin2hex("ABC"));
fwrite(STDOUT, "EDGES DONE\n");
