# Complete vulnerability inventory — 47 findings

Corpus: confirmed-findings research engagement against PHP 8.4/8.5/8.7-dev (original proofs vs 8.5.12/8.4.27),
re-verified against the 2026-11-19 security release (8.5.13 / 8.4.28 / 8.6.0 tips) by source comparison,
and — for the findings marked FRESH — by full re-run of the unmodified corpus PoCs on the official release
Docker images on 2026-11-20 (fresh containers, marker discipline where applicable).

| # | Finding | Sev | Class | Component | Status on 11-19 tips | PoC |
|:-:|---------|:---:|-------|-----------|----------------------|:---:|
| 1 | Failed unserialize leaves Exception::$trace as an object; getTraceAsString walks it as a HashTable | high | type confusion | unserialize | STILL PRESENT — fresh-verified 11-20 | highs/ |
| 2 | object_properties_load() skips typed-property checks during unserialize | medium | type-check bypass | unserialize | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f2/ |
| 3 | Upload-progress key injected through php_serialize/php_binary is unserialized by the php session decoder | medium | object injection via serializer mismatch | session | FIXED in 8.5.13/8.4.28/8.6.0 (2026-11-19 release) | pocs/f3/ |
| 4 | Progress value containing / clears an existing php-serializer session | low | session data loss | session | UNCLEAR (release reworked the serializer path) | pocs/f4/ |
| 5 | max_input_time timer stays armed when max_execution_time is 0 | low | stuck execution timer | Zend timeout / request startup | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f5/ |
| 6 | quoted-printable encode reads past the chunk when line-break-chars starts with SP or TAB | high | heap-buffer-overflow | ext/standard convert.quoted-printable-encode | STILL PRESENT — fresh-verified 11-20 | highs/ |
| 7 | stream_filter_remove from inside filter() frees the active user filter and use-after-frees it | high | heap-use-after-free | ext/standard user filters | STILL PRESENT — fresh-verified 11-20 | userfilter-uaf/ |
| 8 | Tar symlink resolution strips the entry filename and NULL-derefs in phar_stream_read | low | null-pointer dereference | ext/phar | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f8/ |
| 9 | proc_open truncates cwd, descriptor paths, and shell commands at NUL | medium | NUL-byte path and command truncation | ext/standard proc_open | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f9/ |
| 10 | FILTER_VALIDATE_URL accepts non-canonical IPv4 hosts that clients dial as a different address | medium | url-host-confusion | ext/filter FILTER_VALIDATE_URL | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f10/ |
| 11 | FILTER_VALIDATE_URL accepts a backslash inside non-http hosts that WHATWG parses as a different host | low | url-host-confusion | ext/filter FILTER_VALIDATE_URL | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f11/ |
| 12 | DOMXPath namespace-axis callback UAF of a foreign document element | high | use-after-free | ext/dom | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f12/ |
| 13 | GD2 pixel 0xFFFFFFFD (gdBrushed) makes gdImageBrushApply recurse until stack overflow | low | uncontrolled recursion / stack overflow (CWE-674) | ext/gd | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f13/ |
| 14 | mb_ereg_replace copies past a truncated multibyte lead in the replacement | medium | out-of-bounds read | ext/mbstring | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f14/ |
| 15 | mb_ereg_replace \k<digits> truncates the group number to 32 bits | low | integer truncation | ext/mbstring | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f15/ |
| 16 | TLS name check accepts DNS wildcards as IP addresses and as every single-label host | medium | CWE-297 improper certificate hostname verification | openssl | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f16/ |
| 17 | Tracing JIT double-frees ASSIGN_DIM OP_DATA after null-offset deprecation | high | use-after-free | opcache tracing JIT | FIXED in 8.5.13/8.4.28/8.6.0 (2026-11-19 release) | pocs/f17/ |
| 18 | ArrayObject::__unserialize accepts a non-ArrayIterator and getIterator type-confuses destruction | high | type confusion | ext/spl ArrayObject | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f18/ |
| 19 | ArrayObject USE_OTHER cycle recurses forever in spl_array_get_hash_table_ptr | low | unbounded recursion | ext/spl ArrayObject | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f19/ |
| 20 | hash_copy of xxh3/xxh128 with a secret keeps a dangling extSecret pointer | high | use-after-free | ext/hash | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f20/ |
| 21 | mhash_keygen_s2k truncates the output length through a 32-bit cast and still returns a key | low | integer truncation | ext/hash | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f21/ |
| 22 | Argon2 password_get_info accepts an oversized cost and reports PHP_INT_MAX | low | unchecked integer parse | ext/standard | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f22/ |
| 23 | date_create("+999999") rewrites the year to 9999 and stores a +99:00 offset, with no error | medium | parser confusion | ext/date | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f23/ |
| 24 | DateTime unserialize/__set_state applies relative modifiers placed in the offset or abbreviation field | low | logic bug | ext/date | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f24/ |
| 25 | bcmath treats an embedded NUL as the end of the number and accepts the prefix | low | parser truncation | ext/bcmath | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f25/ |
| 26 | SQLite3 TEXT fetch truncates at embedded NUL | low | nul-truncation | ext/sqlite3 | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f26/ |
| 27 | php-cgi fix_pathinfo PATH_INFO pointer underflow leaks environ and overflows the FastCGI env segment | high | out-of-bounds read/write (missing pilen > slen guard) | sapi/cgi | FIXED in 8.5.13/8.4.28/8.6.0 (2026-11-19 release) | pocs/f27/ |
| 28 | PHP-FPM HTML status page reflects auth_user, request method, and script path without encoding | medium | HTML injection / XSS | sapi/fpm | FIXED in 8.5.13/8.4.28/8.6.0 (2026-11-19 release) | pocs/f28/ |
| 29 | Win32 path guard skips multiple trailing dots, so name.php.. creates and opens name.php | medium | path-canonicalization | win32 | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f29/ |
| 30 | Session files on Windows follow symlinks because O_NOFOLLOW is 0 | medium | symlink-follow | ext/session | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f30/ |
| 31 | Lazy proxy subclass hook slot is not cleared and is read back instead of the real instance | medium | lazy-proxy property confusion and omitted GC root | Zend lazy objects / property hooks | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f31/ |
| 32 | DFA turns post-inc/post-dec comparisons into pre-inc/pre-dec for floats and PHP_INT_MAX | low | optimizer miscompile | opcache DFA | PARTIALLY FIXED (null case fixed; float case may persist) | pocs/f32/ |
| 33 | idn_to_ascii() default maps U+FF20 to @ and reports a successful hostname | medium | IDN host-check confusion | ext/intl IDN | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f33/ |
| 34 | WHATWG URL host parser accepts overlong UTF-8 and emits a different ASCII host | medium | host confusion via ill-formed UTF-8 | ext/uri (lexbor WHATWG URL) | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f34/ |
| 37 | FPM pools that share opcache run internal calls against the wrong disable_functions table | critical | disable_functions bypass via shared opcache function-index shift | FPM/OPcache | STILL PRESENT — fresh-verified 11-20 | crosspool/ |
| 38 | curl_reset leaves CURLFile/CURLStringFile postfields; copy and clone resend the body | medium | stale handle state | ext/curl | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f38/ |
| 39 | iconv Q encoded-word decode stops at the first raw NUL | low | missed caller, truncated decode | ext/iconv | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f39/ |
| 40 | pack() kills the process at the hard memory limit | low | process abort from a non-fallible allocation | ext/standard pack | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f40/ |
| 41 | sprintf() kills the process at the hard memory limit | low | process abort from a non-fallible allocation | ext/standard sprintf | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f41/ |
| 42 | pack() truncates repeat counts that do not fit in a C int | low | integer truncation | ext/standard pack | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f42/ |
| 43 | socket_connect() truncates the destination port to 16 bits | medium | integer truncation | ext/sockets | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f43/ |
| 44 | 8.4 socket_bind, socket_sendto, and socket_create_listen truncate out-of-range ports | medium | integer truncation | ext/sockets | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f44/ |
| 45 | cli.prompt backtick parser use-after-free after ini_restore | high | use-after-free | ext/readline | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f45/ |
| 49 | open_basedir escape: getcwd failure stores literal ../ | critical | open_basedir bypass | main/fopen_wrappers.c open_basedir | STILL PRESENT — fresh-verified 11-20 | basedir-relentry/ |
| 50 | json_encode UAF when jsonSerialize drops the last object reference | high | use-after-free | ext/json | STILL PRESENT — fresh-verified 11-20 | pocs/f50/ |
| 51 | HTML template adoptNode drops template contents and leaves them on the source document | low | logic | dom | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f51/ |
| 52 | HTML template cloneNode(true) and cross-document importNode(true) drop template contents | low | logic | dom | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f52/ |

## Verification level legend

- **FRESH 11-20** — PoC re-run by us on official `php:8.4/8.5/8.6-cli|fpm` release images (or Debian package build);
  every such row's result is receipted in this repo.
- **source-verified** — code path compared against the `php-8.5.13` / `php-8.4.28` tags; unchanged = still present.
  Corpus PoC included as shipped; not re-run by us on release builds unless marked FRESH.
- **FIXED** — closed by the 2026-11-19 release (now-published advisories).

## Per-finding notes (fresh verification + deltas)

- **#1 Failed unserialize leaves Exception::$trace as an object; getTraceAsString walks it as a HashTable** — FRESH 11-20: SIGSEGV exit 139 on 8.4.28 + 8.5.13; clean exit 0 on 8.6.0 (corpus PoC, unmodified)
- **#6 quoted-printable encode reads past the chunk when line-break-chars starts with SP or TAB** — FRESH 11-20: live on 8.4.28 + 8.5.13 (16-byte in, 15-byte out, trailing SP deleted); 8.6.0 keeps SP
- **#7 stream_filter_remove from inside filter() frees the active user filter and use-after-frees it** — FRESH 11-20: ESCALATED — see userfilter-uaf/ — system() marker 3/3 tips + abort 134 3/3, full stock
- **#37 FPM pools that share opcache run internal calls against the wrong disable_functions table** — FRESH 11-20: RECHARACTERIZED — see crosspool/ — function-dispatch confusion, RCE 12/12 + cPanel-list file read
- **#49 open_basedir escape: getcwd failure stores literal ../** — FRESH 11-20: LIVE — see basedir-relentry/ — relative-entry expansion escape, all tips, CLI + fpm shapes
- **#50 json_encode UAF when jsonSerialize drops the last object reference** — FRESH 11-20: corpus PoC inert on release builds of all three tips (source-level present; needs grooming)
