# PHP-PrettyHorriblePatches

Independently verified, receipt-backed security research against the current PHP tips: three escalated exploit classes and a full 47-finding corpus re-validation on **8.4.28 / 8.5.13 / 8.6.0** (2026-11-19 security release). Every escalated claim was rebuilt and re-run from scratch on 2026-11-20/21, fresh containers, marker discipline (markers deleted between phases), fresh-compile controls, default ini unless stated.

**Authors:** [@acd421](https://x.com/acd421)
**Date:** 2026-11-20/21 (America/Chicago)
**Currency:** PHP **8.4.28 / 8.5.13 / 8.6.0**, php.net newest as of 2026-11-19; verified inside the images with `PHP_VERSION`.

---

## Escalated findings

| # | Finding | CVSS | Vector | Result | Folder |
|:-:|---------|:----:|--------|--------|--------|
| E1 | Cross-pool opcache function-dispatch confusion | **7.9** | `AV:N/AC:H/PR:L/UI:N/S:C/C:H/I:H/A:H` | `disable_functions` bypass with OS command execution at default ini, 12/12 across all tips + Debian pkg; cPanel-style list gives arbitrary file read on all tips; invoked path gdb-identified (`php_exec` family -> libc `popen`) | `crosspool/` |
| E2 | `open_basedir` relative-entry expansion escape | **7.7** | `AV:N/AC:H/PR:L/UI:N/S:C/C:H/I:H/A:H` | Full filesystem access on all three tips when a relative entry exists in the active basedir. Full-escape state (inherited cwd > PATH_MAX + raw entry) reads `/etc/passwd` outside the jail on 8.4.28 and 8.5.13; 8.6.0 normalizes deep cwd and resists | `basedir-relentry/` |
| E3 | user-filter UAF -> `write_property` hijack -> `system()` | **7.5** | `AV:N/AC:H/PR:L/UI:N/S:U/C:H/I:H/A:H` | **18/18 markers on all three tips under full cPanel-style `disable_functions`**. 8.4/8.5: dual-channel (f1 leak handler fires the exploit). 8.6.0*: direct-fire (f1 confusion fixed there). Gadget-popen, pure-PHP ELF libc resolution, bucket-data reclaim | `userfilter-uaf/` |

**CVSS 3.1 computed from formula.**

### E3 notes

- 8.6.0 uses the direct-fire path (after unserialize) because the f1 confusion is fixed there; the UAF, reclaim, gadget, and popen fire identically. Marked * for the dual-channel leak integration only.
- All tips: fire-once per process. The command executes (marker is ground truth), then the process dies with SIGSEGV during teardown.

---

## Complete Vulnerability Inventory, all 47 findings, each with its PoC

Rows E1/E2/E3 correspond to corpus findings #37/#49/#7.

| # | Finding | Sev | Component | Status on 11-19 tips | PoC |
|:-:|---------|:---:|-----------|----------------------|:---:|
| 37 | FPM pools that share opcache run internal calls against the wrong disable_functions table | **-> E1** | FPM/OPcache | STILL PRESENT, fresh-verified 11-20 | crosspool/ |
| 49 | open_basedir escape: getcwd failure stores literal ../ | **-> E2** | main/fopen_wrappers.c open_basedir | STILL PRESENT, fresh-verified 11-20 | basedir-relentry/ |
| 1 | Failed unserialize leaves Exception::$trace as an object; getTraceAsString walks it as a HashTable | **high** | unserialize | STILL PRESENT, fresh-verified 11-20 | highs/ |
| 6 | quoted-printable encode reads past the chunk when line-break-chars starts with SP or TAB | **high** | ext/standard convert.quoted-printable-en | STILL PRESENT, fresh-verified 11-20 | highs/ |
| 7 | stream_filter_remove from inside filter() frees the active user filter and use-after-frees it | **-> E3** | ext/standard user filters | STILL PRESENT, fresh-verified 11-20 | userfilter-uaf/ |
| 12 | DOMXPath namespace-axis callback UAF of a foreign document element | **high** | ext/dom | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f12/ |
| 17 | Tracing JIT double-frees ASSIGN_DIM OP_DATA after null-offset deprecation | **high** | opcache tracing JIT | FIXED 11-19 release | pocs/f17/ |
| 18 | ArrayObject::__unserialize accepts a non-ArrayIterator and getIterator type-confuses destruction | **high** | ext/spl ArrayObject | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f18/ |
| 20 | hash_copy of xxh3/xxh128 with a secret keeps a dangling extSecret pointer | **high** | ext/hash | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f20/ |
| 27 | php-cgi fix_pathinfo PATH_INFO pointer underflow leaks environ and overflows the FastCGI env segment | **high** | sapi/cgi | FIXED 11-19 release | pocs/f27/ |
| 45 | cli.prompt backtick parser use-after-free after ini_restore | **high** | ext/readline | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f45/ |
| 50 | json_encode UAF when jsonSerialize drops the last object reference | **high** | ext/json | STILL PRESENT, fresh-verified 11-20 | pocs/f50/ |
| 2 | object_properties_load() skips typed-property checks during unserialize | med | unserialize | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f2/ |
| 3 | Upload-progress key injected through php_serialize/php_binary is unserialized by the php session decoder | med | session | FIXED 11-19 release | pocs/f3/ |
| 9 | proc_open truncates cwd, descriptor paths, and shell commands at NUL | med | ext/standard proc_open | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f9/ |
| 10 | FILTER_VALIDATE_URL accepts non-canonical IPv4 hosts that clients dial as a different address | med | ext/filter FILTER_VALIDATE_URL | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f10/ |
| 14 | mb_ereg_replace copies past a truncated multibyte lead in the replacement | med | ext/mbstring | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f14/ |
| 16 | TLS name check accepts DNS wildcards as IP addresses and as every single-label host | med | openssl | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f16/ |
| 23 | date_create("+999999") rewrites the year to 9999 and stores a +99:00 offset, with no error | med | ext/date | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f23/ |
| 28 | PHP-FPM HTML status page reflects auth_user, request method, and script path without encoding | med | sapi/fpm | FIXED 11-19 release | pocs/f28/ |
| 29 | Win32 path guard skips multiple trailing dots, so name.php.. creates and opens name.php | med | win32 | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f29/ |
| 30 | Session files on Windows follow symlinks because O_NOFOLLOW is 0 | med | ext/session | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f30/ |
| 31 | Lazy proxy subclass hook slot is not cleared and is read back instead of the real instance | med | Zend lazy objects / property hooks | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f31/ |
| 33 | idn_to_ascii() default maps U+FF20 to @ and reports a successful hostname | med | ext/intl IDN | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f33/ |
| 34 | WHATWG URL host parser accepts overlong UTF-8 and emits a different ASCII host | med | ext/uri (lexbor WHATWG URL) | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f34/ |
| 38 | curl_reset leaves CURLFile/CURLStringFile postfields; copy and clone resend the body | med | ext/curl | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f38/ |
| 43 | socket_connect() truncates the destination port to 16 bits | med | ext/sockets | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f43/ |
| 44 | 8.4 socket_bind, socket_sendto, and socket_create_listen truncate out-of-range ports | med | ext/sockets | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f44/ |
| 4 | Progress value containing / clears an existing php-serializer session | low | session | UNCLEAR | pocs/f4/ |
| 5 | max_input_time timer stays armed when max_execution_time is 0 | low | Zend timeout / request startup | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f5/ |
| 8 | Tar symlink resolution strips the entry filename and NULL-derefs in phar_stream_read | low | ext/phar | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f8/ |
| 11 | FILTER_VALIDATE_URL accepts a backslash inside non-http hosts that WHATWG parses as a different host | low | ext/filter FILTER_VALIDATE_URL | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f11/ |
| 13 | GD2 pixel 0xFFFFFFFD (gdBrushed) makes gdImageBrushApply recurse until stack overflow | low | ext/gd | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f13/ |
| 15 | mb_ereg_replace \k<digits> truncates the group number to 32 bits | low | ext/mbstring | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f15/ |
| 19 | ArrayObject USE_OTHER cycle recurses forever in spl_array_get_hash_table_ptr | low | ext/spl ArrayObject | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f19/ |
| 21 | mhash_keygen_s2k truncates the output length through a 32-bit cast and still returns a key | low | ext/hash | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f21/ |
| 22 | Argon2 password_get_info accepts an oversized cost and reports PHP_INT_MAX | low | ext/standard | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f22/ |
| 24 | DateTime unserialize/__set_state applies relative modifiers placed in the offset or abbreviation field | low | ext/date | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f24/ |
| 25 | bcmath treats an embedded NUL as the end of the number and accepts the prefix | low | ext/bcmath | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f25/ |
| 26 | SQLite3 TEXT fetch truncates at embedded NUL | low | ext/sqlite3 | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f26/ |
| 32 | DFA turns post-inc/post-dec comparisons into pre-inc/pre-dec for floats and PHP_INT_MAX | low | opcache DFA | PARTIAL | pocs/f32/ |
| 39 | iconv Q encoded-word decode stops at the first raw NUL | low | ext/iconv | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f39/ |
| 40 | pack() kills the process at the hard memory limit | low | ext/standard pack | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f40/ |
| 41 | sprintf() kills the process at the hard memory limit | low | ext/standard sprintf | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f41/ |
| 42 | pack() truncates repeat counts that do not fit in a C int | low | ext/standard pack | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f42/ |
| 51 | HTML template adoptNode drops template contents and leaves them on the source document | low | dom | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f51/ |
| 52 | HTML template cloneNode(true) and cross-document importNode(true) drop template contents | low | dom | STILL PRESENT (source-verified vs 8.5.13/8.4.28) | pocs/f52/ |

### Verification levels

- **fresh-verified 11-20**: re-run on official release images; results receipted (#1, #6, #7, #37, #49, #50).
- **source-verified**: compared against the 8.5.13/8.4.28 tags; unchanged = still present.
- **FIXED**: closed by the 2026-11-19 release.

### Fresh-verification notes

- **#37 / E1**: cross-pool function-dispatch confusion. Default ini. gdb-identified invocation.
- **#49 / E2**: relative-entry escape. Full-escape on 8.4.28 + 8.5.13 (deep cwd + raw entry). 8.6.0 resists.
- **#7 / E3**: user-filter UAF -> `system()` on all tips, 18/18 under full disable_functions. Dual-channel on 8.4/8.5; direct-fire on 8.6.0.
- **#1**: SIGSEGV 139 on 8.4.28 + 8.5.13; clean on 8.6.0.
- **#6**: live on 8.4.28 + 8.5.13; 8.6.0 keeps the SP.
- **#50**: inert on release builds.

---

## Root causes

- **E1:** `zend_disable_functions()` + `zend_hash_rehash()` compacts the function table; `ZEND_INIT_FCALL` type-specialized handler resolves via baked bucket index with no name validation.
- **E2:** `open_basedir` accepts relative entries, expanded once at startup against the daemon's cwd.
- **E3:** `php_user_filter::filter()` calling `stream_filter_remove()` on itself frees the filter while `user_filters.c:225` runs `zend_update_property_null` over the freed abstract post-callback. Bucket reclaim + `/proc` pointer patch + gadget-popen.

## Weapons

| Weapon | What it does |
|--------|-------------|
| `crosspool/poc/fcgi_client.py` | FastCGI responder client |
| `crosspool/poc/run_multi1.sh` | E1 RCE check |
| `basedir-relentry/poc/poc-f49d.php` | E2 CLI climb |
| `basedir-relentry/poc/poc-f49win3.php` | E2 full-escape probe |
| `userfilter-uaf/poc/ip_noffi_v12.php` | **E3 end-to-end dual-channel exploit: 18/18, all tips** |
| `pocs/f1...f52` | All 47 corpus PoCs |

## Receipts

| Target | Chain | Receipt |
|--------|-------|---------|
| php:8.4-fpm, default ini | E1 | **marker all tips** |
| php:8.4-fpm, cPanel list | E1 readfile | **`/etc/passwd` dump** |
| php:8.4/8.5-cli, `-d jail:../` | E2 | **CWD=/ + `/etc/passwd`** |
| php:8.4-cli, deep cwd + raw entry | E2 full escape | **`/etc/passwd` outside jail** |
| **php:8.4/8.5/8.6-cli, full cPanel disable_functions** | **E3** | **18/18 MARKER** |

## Disclaimer

This material is security research for **defensive** and **authorized** testing only.

- **PHP (the project / PHP Group / related foundations):** Published so operators and maintainers can assess and patch.
- **CISA and similar government and standards bodies:** Coordinated, defensive research context.
- **Cyber threat intelligence firms and defenders:** Status tables support detection and remediation. No reuse for malicious operations.

Unauthorized access to computer systems is illegal. The authors assume no liability for misuse.

## Credits

| Contribution | Credited to |
|-------------|-------------|
| All findings, verification, harnesses, receipts | [@acd421](https://x.com/acd421) |
| RCE research assistance, E3 user-filter UAF -> `write_property` hijack -> `system()` | [@GGSec](https://x.com/GGASecDe) |

## License

MIT (see LICENSE).