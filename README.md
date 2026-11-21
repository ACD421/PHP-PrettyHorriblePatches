# PHP-PrettyHorriblePatches

Independently verified, receipt-backed security research against the current PHP tips: three escalated exploit classes and a full 47-finding corpus re-validation on **8.4.28 / 8.5.13 / 8.6.0** (2026-11-19 security release). Every escalated claim was rebuilt and re-run from scratch on 2026-11-20, fresh containers, marker discipline (markers deleted between phases), fresh-compile controls.

**Authors:** [@acd421](https://x.com/acd421)
**Date:** 2026-11-20 (America/Chicago)
**Currency:** PHP **8.4.28 / 8.5.13 / 8.6.0**, php.net newest as of 2026-11-19; verified inside the images with `PHP_VERSION`, never trusted from tags. Also tested: Debian trixie `php-fpm8.4` package build `8.4.28-1` (built Nov 18 2026).

---

## Escalated findings (computed CVSS 3.1, vectors shown)

| # | Finding | CVSS | Vector | Result | Folder |
|:-:|---------|:----:|--------|--------|--------|
| E1 | Cross-pool opcache function-dispatch confusion | **7.9** | `AV:N/AC:H/PR:L/UI:N/S:C/C:H/I:H/A:H` | `disable_functions` bypass with OS command execution, 12/12 across all three tips + Debian package build; realistic cPanel-style list → arbitrary file read | `crosspool/` |
| E2 | `open_basedir` relative-entry expansion escape | **9.2** | `AV:N/AC:L/PR:L/UI:N/S:C/C:H/I:H/A:H` | Full filesystem access, all three tips (CLI `-d` shape + real FPM pool demo) | `basedir-relentry/` |
| E3 | user-filter UAF → `write_property` hijack → `system()` | **7.5** | `AV:N/AC:H/PR:L/UI:N/S:U/C:H/I:H/A:H` | 6/6 on stock release builds, `system()` marker ×3 tips, `abort()` exit 134 ×3 tips; default Zend MM, FFI off | `userfilter-uaf/` |

**CVSS 3.1 computed from formula.** Vector arithmetic: E = 8.22·AV·AC·PR·UI; ISS = 1−(1−C)(1−I)(1−A); Impact(S:U) = 6.42·ISS, Impact(S:C) = 7.52·(ISS−0.029)−3.25·(ISS−0.02)^15; Base = Roundup₁₀(Impact+E).

---

## Complete Vulnerability Inventory, all 47 findings, each with its PoC

Corpus: confirmed-findings research engagement (original proofs vs 8.5.12/8.4.27), re-verified against the 2026-11-19 tips. Rows E1/E3/E2 correspond to corpus findings #37/#7/#49, see the escalated table above and their deep-dive folders.

| # | Finding | Sev | Component | Status on 11-19 tips | PoC |
|:-:|---------|:---:|-----------|----------------------|:---:|
| 1 | Failed unserialize leaves Exception::$trace as an object; getTraceAsString walks it as a HashTable | **high** | unserialize | 🔴 Still present, fresh-verified 11-20 | highs/ |
| 2 | object_properties_load() skips typed-property checks during unserialize | med | unserialize | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f2/ |
| 3 | Upload-progress key injected through php_serialize/php_binary is unserialized by the php session decoder | med | session | 🟢 Fixed 11-19 release | pocs/f3/ |
| 4 | Progress value containing / clears an existing php-serializer session | low | session | 🟡 Unclear | pocs/f4/ |
| 5 | max_input_time timer stays armed when max_execution_time is 0 | low | Zend timeout / request startup | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f5/ |
| 6 | quoted-printable encode reads past the chunk when line-break-chars starts with SP or TAB | **high** | ext/standard convert.quoted-printable-en | 🔴 Still present, fresh-verified 11-20 | highs/ |
| 7 | stream_filter_remove from inside filter() frees the active user filter and use-after-frees it | **→ E3** | ext/standard user filters | 🔴 Still present, fresh-verified 11-20 | userfilter-uaf/ |
| 8 | Tar symlink resolution strips the entry filename and NULL-derefs in phar_stream_read | low | ext/phar | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f8/ |
| 9 | proc_open truncates cwd, descriptor paths, and shell commands at NUL | med | ext/standard proc_open | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f9/ |
| 10 | FILTER_VALIDATE_URL accepts non-canonical IPv4 hosts that clients dial as a different address | med | ext/filter FILTER_VALIDATE_URL | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f10/ |
| 11 | FILTER_VALIDATE_URL accepts a backslash inside non-http hosts that WHATWG parses as a different host | low | ext/filter FILTER_VALIDATE_URL | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f11/ |
| 12 | DOMXPath namespace-axis callback UAF of a foreign document element | **high** | ext/dom | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f12/ |
| 13 | GD2 pixel 0xFFFFFFFD (gdBrushed) makes gdImageBrushApply recurse until stack overflow | low | ext/gd | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f13/ |
| 14 | mb_ereg_replace copies past a truncated multibyte lead in the replacement | med | ext/mbstring | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f14/ |
| 15 | mb_ereg_replace \k<digits> truncates the group number to 32 bits | low | ext/mbstring | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f15/ |
| 16 | TLS name check accepts DNS wildcards as IP addresses and as every single-label host | med | openssl | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f16/ |
| 17 | Tracing JIT double-frees ASSIGN_DIM OP_DATA after null-offset deprecation | **high** | opcache tracing JIT | 🟢 Fixed 11-19 release | pocs/f17/ |
| 18 | ArrayObject::__unserialize accepts a non-ArrayIterator and getIterator type-confuses destruction | **high** | ext/spl ArrayObject | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f18/ |
| 19 | ArrayObject USE_OTHER cycle recurses forever in spl_array_get_hash_table_ptr | low | ext/spl ArrayObject | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f19/ |
| 20 | hash_copy of xxh3/xxh128 with a secret keeps a dangling extSecret pointer | **high** | ext/hash | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f20/ |
| 21 | mhash_keygen_s2k truncates the output length through a 32-bit cast and still returns a key | low | ext/hash | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f21/ |
| 22 | Argon2 password_get_info accepts an oversized cost and reports PHP_INT_MAX | low | ext/standard | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f22/ |
| 23 | date_create("+999999") rewrites the year to 9999 and stores a +99:00 offset, with no error | med | ext/date | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f23/ |
| 24 | DateTime unserialize/__set_state applies relative modifiers placed in the offset or abbreviation field | low | ext/date | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f24/ |
| 25 | bcmath treats an embedded NUL as the end of the number and accepts the prefix | low | ext/bcmath | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f25/ |
| 26 | SQLite3 TEXT fetch truncates at embedded NUL | low | ext/sqlite3 | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f26/ |
| 27 | php-cgi fix_pathinfo PATH_INFO pointer underflow leaks environ and overflows the FastCGI env segment | **high** | sapi/cgi | 🟢 Fixed 11-19 release | pocs/f27/ |
| 28 | PHP-FPM HTML status page reflects auth_user, request method, and script path without encoding | med | sapi/fpm | 🟢 Fixed 11-19 release | pocs/f28/ |
| 29 | Win32 path guard skips multiple trailing dots, so name.php.. creates and opens name.php | med | win32 | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f29/ |
| 30 | Session files on Windows follow symlinks because O_NOFOLLOW is 0 | med | ext/session | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f30/ |
| 31 | Lazy proxy subclass hook slot is not cleared and is read back instead of the real instance | med | Zend lazy objects / property hooks | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f31/ |
| 32 | DFA turns post-inc/post-dec comparisons into pre-inc/pre-dec for floats and PHP_INT_MAX | low | opcache DFA | 🟡 Partial | pocs/f32/ |
| 33 | idn_to_ascii() default maps U+FF20 to @ and reports a successful hostname | med | ext/intl IDN | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f33/ |
| 34 | WHATWG URL host parser accepts overlong UTF-8 and emits a different ASCII host | med | ext/uri (lexbor WHATWG URL) | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f34/ |
| 37 | FPM pools that share opcache run internal calls against the wrong disable_functions table | **→ E1** | FPM/OPcache | 🔴 Still present, fresh-verified 11-20 | crosspool/ |
| 38 | curl_reset leaves CURLFile/CURLStringFile postfields; copy and clone resend the body | med | ext/curl | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f38/ |
| 39 | iconv Q encoded-word decode stops at the first raw NUL | low | ext/iconv | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f39/ |
| 40 | pack() kills the process at the hard memory limit | low | ext/standard pack | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f40/ |
| 41 | sprintf() kills the process at the hard memory limit | low | ext/standard sprintf | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f41/ |
| 42 | pack() truncates repeat counts that do not fit in a C int | low | ext/standard pack | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f42/ |
| 43 | socket_connect() truncates the destination port to 16 bits | med | ext/sockets | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f43/ |
| 44 | 8.4 socket_bind, socket_sendto, and socket_create_listen truncate out-of-range ports | med | ext/sockets | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f44/ |
| 45 | cli.prompt backtick parser use-after-free after ini_restore | **high** | ext/readline | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f45/ |
| 49 | open_basedir escape: getcwd failure stores literal ../ | **→ E2** | main/fopen_wrappers.c open_basedir | 🔴 Still present, fresh-verified 11-20 | basedir-relentry/ |
| 50 | json_encode UAF when jsonSerialize drops the last object reference | **high** | ext/json | 🔴 Still present, fresh-verified 11-20 | pocs/f50/ |
| 51 | HTML template adoptNode drops template contents and leaves them on the source document | low | dom | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f51/ |
| 52 | HTML template cloneNode(true) and cross-document importNode(true) drop template contents | low | dom | 🔴 Still present (source-verified vs 8.5.13/8.4.28) | pocs/f52/ |

### Verification levels

- **fresh-verified 11-20**, corpus PoC re-run unmodified on official `php:8.4/8.5/8.6-cli|fpm` release images (or Debian package build); results receipted in this repo (#1, #6, #7, #37, #49, #50).
- **source-verified**, code path compared against the `php-8.5.13` / `php-8.4.28` tags; unchanged = still present. Corpus PoC included as shipped.
- **Fixed**, closed by the 2026-11-19 release (now-published advisories: #3 = GHSA-658j-r8rw-q6mx/CVE-2026-115560, #17 = GH-24437, #27 = CVE-2026-129538, #28 = CVE-2026-129292).

### Fresh-verification notes

- **#37 / E1**, the underlying mechanism is cross-pool function-dispatch confusion: deterministic per (build, config, script), the disable-list composition selects the invoked builtin, and the map is enumerable offline via a `get_defined_functions()` table-order diff.
- **#49 / E2**, live on every tip; verified with the full PoC harness shapes (prebuilt directory tree, exact launch flags, FPM pool configuration).
- **#7 / E3**, escalated to full `system()` execution on all three tips, stock release builds.
- **#1**, SIGSEGV exit 139 on 8.4.28 + 8.5.13; clean exit 0 on 8.6.0 (unmodified corpus PoC).
- **#6**, live on 8.4.28 + 8.5.13 (16-byte in → 15-byte out, trailing SP deleted); 8.6.0 keeps the SP with this shape.
- **#50**, corpus PoC does not fire on release builds of the current tips (code path present at source level; exploitation requires additional heap grooming).

---

## Root causes (escalated findings)

- **E1:** `zend_disable_functions()` (`Zend/zend_API.c:3682`) deletes entries then `zend_hash_rehash()` compacts the table, while opcache's SHM-shared call slots and the `ZEND_INIT_FCALL` type-specialized handler (`Zend/zend_vm_def.h:4034`) resolve via a baked bucket index into the executing pool's table with no name validation; the plain handler's null check is a release-elided `ZEND_ASSERT`. Pools differing in `disable_functions` invoke wrong builtins with attacker-controlled arguments.
- **E1 caveat:** `ini_get('disable_functions')` is constant-folded at compile, a pool executing another pool's cached script prints the compiling pool's value; not evidence of executing-pool config.
- **E2:** `open_basedir` accepts relative entries, expanded once against the daemon's startup cwd; php-fpm masters launch from `/`. Runtime `ini_set` widen stores a literal `../` but does not itself escape (climb stalls at the jail boundary), the live vector is the config-level relative entry.
- **E3:** `php_user_filter::filter()` calling `stream_filter_remove()` on itself frees the filter while `ext/standard/user_filters.c:225` runs `zend_update_property_null` over the freed abstract post-callback. The wrapper reads the abstract slot as a direct `zend_object*`, dispatches `handlers->write_property` (+0x28). Full-bin string reclaim + `/proc/self/mem` slot patch + kept obj-host string → `system(cmd)`. Patch only the abstract slot, `abort()` survives a corrupted heap, `system()` does not.

## Weapons

| Weapon | What it does |
|--------|-------------|
| `crosspool/poc/fcgi_client.py` | Dependency-free FastCGI responder client (stdout+stderr capture) |
| `crosspool/poc/run_multi1.sh` | Marker-disciplined RCE matrix: B-warm → marker delete → A-fire |
| `crosspool/poc/oracle methodology` | `get_defined_functions()` diff → offline confusion-map computation |
| `basedir-relentry/poc/poc-f49d.php` | `-d open_basedir=jail:../` climb-to-root + outside reads/includes |
| `basedir-relentry/poc/fpm-bd.conf` + `bdprobe.php` | Real FPM pool with one relative entry → `/etc/passwd` through the response |
| `userfilter-uaf/poc/ip_noffi_v7.php` | End-to-end UAF → `write_property` → `system()` / `abort()`, pure PHP |
| `pocs/f1…f52` | All 47 corpus PoCs, as shipped |

```
# E1 (RCE matrix, per tip)
docker run -d --name c2 -p 19001:9001 -p 19002:9002 -v $PWD:/www -v $PWD/conf:/cconf:ro \
  php:8.4-fpm php-fpm -F -y /cconf/fpm-multi1.conf -d opcache.enable=1 -d opcache.file_update_protection=0
bash crosspool/poc/run_multi1.sh 8.4   # expect: marker written by disabled pool A

# E2 (CLI shape)
php -n -d open_basedir=/tmp/f49/jail:../ basedir-relentry/poc/poc-f49d.php   # CWD=/ + /etc/passwd read

# E3
docker run --rm -v $PWD:/h -e F7_TARGET=system -e F7_CMD="touch /tmp/PWNED" \
  php:8.6-cli php userfilter-uaf/poc/ip_noffi_v7.php                     # marker + crash-after (command executed)
```

## Receipts

| Target | Chain | Receipt |
|--------|-------|---------|
| php:8.4-fpm (8.4.28, official) | E1 `{exec,shell_exec}` | **marker by disabled pool A, 3/3** |
| php:8.5-fpm (8.5.13, official) | E1 | **marker 3/3** |
| php:8.6-fpm (8.6.0, official) | E1 | **marker 3/3** |
| Debian trixie php-fpm8.4 8.4.28-1 (distro build) | E1 | **marker 3/3** |
| php:8.4-fpm, 10-name cPanel-style disable list | E1 `passthru()` → `readfile()` | **full `/etc/passwd` dump through pool A response** |
| php:8.4/8.5/8.6-cli | E2 `jail:../` climb | **CWD=/, outside file + `/etc/passwd` + include executed** |
| php:8.4-fpm pool `open_basedir=/www:../` | E2 fpm shape | `/etc/passwd` + `/etc/hostname` through response |
| php:8.4/8.5/8.6-cli (stock, default MM, FFI off) | E3 `system()` | **marker 3/3** |
| php:8.4/8.5/8.6-cli | E3 `abort()` | **exit 134, 3/3** |
| php:8.4/8.5-cli | #1 Exception-trace confusion | SIGSEGV exit 139 (heap addresses as frame indexes) |
| php:8.4/8.5-cli | #6 quoted-printable OOB | 16-byte input → 15-byte output, trailing SP deleted |

## Build / Environment

```
docker pull php:8.4-fpm && docker run --rm php:8.4-fpm php -r 'echo PHP_VERSION;'   # 8.4.28
# Debian distro build:
docker run -it debian:trixie bash -c 'apt-get update && apt-get install -y php-fpm php-cli && php -v'
```

All escalated findings verified on default configuration unless stated: default Zend MM, FFI disabled, no `USE_ZEND_ALLOC` override, no ASan, opcache on (package default for FPM).

## Credits

| Contribution | Credited to |
|-------------|-------------|
| All findings, verification, harnesses, receipts | [@acd421](https://x.com/acd421) |
| RCE research assistance, E3 user-filter UAF → `write_property` hijack → `system()` | [@GGSec](https://x.com/GGASecDe) |

## Disclaimer

This material is security research for **defensive** and **authorized** testing only. It is not permission or encouragement to attack systems you do not own or are not explicitly authorized to test.

- **PHP (the project / PHP Group / related foundations):** Findings, root-cause notes, status tables, and reproduction material are published so operators and maintainers can assess and patch. Do not use this material to compromise production PHP deployments without authorization.
- **CISA and similar government and standards bodies:** Treat this as coordinated, defensive research context intended to support vulnerability management and prioritization, not as a playbook for harmful activity against critical infrastructure or government systems.
- **Cyber threat intelligence firms and defenders:** Status tables and root-cause summaries are provided to support detection, prioritization, and remediation. Redistribution or reuse of this material for malicious operations is prohibited.

Unauthorized access to computer systems is illegal. The authors assume no liability for misuse.

## License

MIT.