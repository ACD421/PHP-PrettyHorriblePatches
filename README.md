# PHP-PrettyHorriblePatches

Independently verified, receipt-backed security research against the current PHP tips: two escalated exploit classes, one documented configuration footgun, and a full 47-finding corpus re-validation on **8.4.28 / 8.5.13 / 8.6.0** (2026-11-19 security release). Every escalated claim was rebuilt and re-run from scratch on 2026-11-20, fresh containers, marker discipline (markers deleted between phases), fresh-compile controls, default ini unless stated.

**Authors:** [@acd421](https://x.com/acd421)
**Date:** 2026-11-20 (America/Chicago)
**Currency:** PHP **8.4.28 / 8.5.13 / 8.6.0**, php.net newest as of 2026-11-19; verified inside the images with `PHP_VERSION`, never trusted from tags. Also tested: Debian trixie `php-fpm8.4` package build `8.4.28-1` (built Nov 18 2026).

---

## Escalated findings (computed CVSS 3.1, vectors shown)

| # | Finding | CVSS | Vector | Result | Folder |
|:-:|---------|:----:|--------|--------|--------|
| E1 | Cross-pool opcache function-dispatch confusion | **7.9** | `AV:N/AC:H/PR:L/UI:N/S:C/C:H/I:H/A:H` | `disable_functions` bypass with OS command execution at **default ini**, 12/12 across all three tips + Debian package build; realistic cPanel-style list gives arbitrary file read (`/etc/passwd` dump, all three tips); invoked path gdb-identified (`php_exec` family -> libc `popen`) | `crosspool/` |
| E2 | user-filter UAF -> `write_property` hijack -> `system()` | **7.5** | `AV:N/AC:H/PR:L/UI:N/S:U/C:H/I:H/A:H` | 6/6 run **under a full cPanel-style `disable_functions` list**: `system()` marker on all three tips, `abort()` exit 134 on all three; no shell calls in the PoC (pure-PHP ELF libc resolution); default Zend MM, FFI off | `userfilter-uaf/` |

**CVSS 3.1 computed from formula.** E = 8.22*AV*AC*PR*UI; ISS = 1-(1-C)(1-I)(1-A); Impact(S:U) = 6.42*ISS, Impact(S:C) = 7.52*(ISS-0.029)-3.25*(ISS-0.02)^15; Base = Roundup10(Impact+E).

### Execution semantics

- E2's `system()` variant is fire-once per process: the command executes (marker is ground truth), then the process dies with SIGSEGV during teardown. The `abort()` variant exits 134 cleanly and is the control-flow proof.
- E1 execution is silent: the payload's own output redirection plus the `php_exec`/`popen` path produce no response output; the marker is the ground truth.

---

## Configuration footgun (documented, not escalated)

`open_basedir` accepts **relative entries**, which are expanded once at startup against the daemon's cwd. php-fpm masters launch from `/`, so one relative entry in a pool config (for example `/www:../`) expands to the filesystem root and the pool effectively has no basedir. This requires an operator-written relative entry (PHP documents entries as absolute paths), so we document it as a hardening footgun with no CVSS rather than an escalated finding. The runtime `ini_set` widen stores a literal `../` but does not itself escape (climb stalls at the jail boundary, tested on all three tips). Details and PoCs: `basedir-relentry/`.

---

## Complete Vulnerability Inventory, all 47 findings, each with its PoC

Corpus: confirmed-findings research engagement (original proofs vs 8.5.12/8.4.27), re-verified against the 2026-11-19 tips. Rows E1/E2 correspond to corpus findings #37/#7; corpus finding #49 is the configuration footgun above.

| # | Finding | Sev | Component | Status on 11-19 tips | PoC |
|:-:|---------|:---:|-----------|----------------------|:---:|
| 37 | FPM pools that share opcache run internal calls against the wrong disable_functions table | **-> E1** | FPM/OPcache | STILL PRESENT, fresh-verified 11-20 | crosspool/ |
| 49 | open_basedir escape: getcwd failure stores literal ../ | **footgun** | main/fopen_wrappers.c open_basedir | STILL PRESENT, fresh-verified 11-20 | basedir-relentry/ |
| 1 | Failed unserialize leaves Exception::$trace as an object; getTraceAsString walks it as a HashTable | **high** | unserialize | STILL PRESENT, fresh-verified 11-20 | highs/ |
| 6 | quoted-printable encode reads past the chunk when line-break-chars starts with SP or TAB | **high** | ext/standard convert.quoted-printable-en | STILL PRESENT, fresh-verified 11-20 | highs/ |
| 7 | stream_filter_remove from inside filter() frees the active user filter and use-after-frees it | **-> E2** | ext/standard user filters | STILL PRESENT, fresh-verified 11-20 | userfilter-uaf/ |
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

- **fresh-verified 11-20**, corpus PoC re-run unmodified on official `php:8.4/8.5/8.6-cli|fpm` release images (or Debian package build); results receipted in this repo (#1, #6, #7, #37, #49, #50).
- **source-verified**, code path compared against the `php-8.5.13` / `php-8.4.28` tags; unchanged = still present. Corpus PoC included as shipped, not re-run by us.
- **Fixed**, closed by the 2026-11-19 release (advisories: #3 = GHSA-658j-r8rw-q6mx/CVE-2026-115560, #17 = GH-24437, #27 = CVE-2026-129538, #28 = CVE-2026-129292).

### Fresh-verification notes

- **#37 / E1**, cross-pool function-dispatch confusion: deterministic per (build, config, script); the disable-list composition selects the invoked builtin; the map is enumerable offline via a `get_defined_functions()` table-order diff; works at default ini; invoked path gdb-identified (`php_exec` family -> libc `popen`).
- **#7 / E2**, user-filter UAF escalated to `system()` on all three tips, run under a full cPanel-style disable_functions list, with libc offsets resolved by pure-PHP ELF parsing (no shell calls).
- **#49**, configuration footgun (see above): live on every tip when a relative basedir entry exists; runtime ini_set path stores the literal but does not escape.
- **#1**, SIGSEGV exit 139 on 8.4.28 + 8.5.13; clean exit 0 on 8.6.0 (unmodified corpus PoC).
- **#6**, live on 8.4.28 + 8.5.13 (16-byte in -> 15-byte out, trailing SP deleted); 8.6.0 keeps the SP with this shape.
- **#50**, corpus PoC does not fire on release builds of the current tips (code path present at source level; exploitation requires additional heap grooming).

---

## Root causes (escalated findings)

- **E1:** `zend_disable_functions()` (`Zend/zend_API.c:3682`) deletes entries then `zend_hash_rehash()` compacts the table, while opcache's SHM-shared call slots and the `ZEND_INIT_FCALL` type-specialized handler (`Zend/zend_vm_def.h:4034`) resolve via a baked bucket index into the executing pool's table with no name validation; the plain handler's null check is a release-elided `ZEND_ASSERT`. Pools differing in `disable_functions` invoke wrong builtins with attacker-controlled arguments. gdb receipt: `execute_ex -> zif shim -> php_exec -> popen` in the hardened pool.
- **E1 caveat:** `ini_get('disable_functions')` is constant-folded at compile, a pool executing another pool's cached script prints the compiling pool's value; not evidence of executing-pool config.
- **E2:** `php_user_filter::filter()` calling `stream_filter_remove()` on itself frees the filter while `ext/standard/user_filters.c:225` runs `zend_update_property_null` over the freed abstract post-callback. The wrapper reads the abstract slot as a direct `zend_object*`, dispatches `handlers->write_property` (+0x28). Full-bin string reclaim + `/proc/self/mem` slot patch + kept obj-host string -> `system(cmd)`. Patch only the abstract slot: `abort()` survives a corrupted heap, `system()` does not.

## Weapons

| Weapon | What it does |
|--------|-------------|
| `crosspool/poc/fcgi_client.py` | Dependency-free FastCGI responder client (stdout+stderr capture) |
| `crosspool/poc/run_multi1.sh` | Marker-disciplined E1 RCE check (portable, default ini) |
| `crosspool/poc/run_gt3.sh` | E1 exec-family map per tip (portable) |
| `crosspool/poc/oracle methodology` | `get_defined_functions()` diff -> offline confusion-map computation |
| `basedir-relentry/poc/poc-f49d.php` | CLI footgun receipt shape (climb + outside reads/includes) |
| `userfilter-uaf/poc/ip_noffi_v8.php` | E2 end-to-end: UAF -> `write_property` -> `system()`/`abort()`, runs under disable_functions, pure PHP |
| `pocs/f1...f52` | All 47 corpus PoCs, as shipped |

```
# E1 (RCE check, default ini, per tip)
cd crosspool/poc && bash run_multi1.sh 8.4     # marker written by disabled pool A

# E2 (under the full cPanel-style disable_functions list)
docker run --rm -v $PWD:/h -e F7_TARGET=system -e F7_CMD="touch /tmp/PWNED" \
  php:8.6-cli php -d disable_functions=exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source /h/userfilter-uaf/poc/ip_noffi_v8.php
```

## Receipts

| Target | Chain | Receipt |
|--------|-------|---------|
| php:8.4/8.5/8.6-fpm (official), default ini | E1 `{exec,shell_exec}` | **marker by disabled pool A, all three tips** (`e1_default_whm.log`) |
| php:8.4/8.5/8.6-fpm (official), explicit ini | E1 | **marker 3/3 each, 12/12 total incl. Debian pkg** (`multi1_matrix.log`) |
| Debian trixie php-fpm8.4 8.4.28-1 (distro build) | E1 | **marker 3/3** |
| php:8.4/8.5/8.6-fpm, 10-name cPanel-style list | E1 `passthru()` -> `readfile()` | **`/etc/passwd` dump through pool A response, all three tips** (`e1_default_whm.log`) |
| php:8.4-fpm pool A worker under gdb | E1 identification | `popen` breakpoint hit via `php_exec` from `execute_ex`; marker written |
| php:8.4/8.5/8.6-cli, full cPanel disable list, default MM, FFI off | E2 `system()` | **marker 3/3, PHP_EXIT=139 after execution** (`f7v8_hardened.log`) |
| php:8.4/8.5/8.6-cli, full cPanel disable list | E2 `abort()` | **PHP_EXIT=134, 3/3** (`f7v8_hardened.log`) |
| php:8.4/8.5/8.6-cli | #1 Exception-trace confusion | SIGSEGV exit 139 on 8.4.28 + 8.5.13, exit 0 on 8.6.0 (`highs_receipts.log`) |
| php:8.4/8.5-cli | #6 quoted-printable OOB | 16-byte input -> 15-byte output, trailing SP deleted (`gt3` family maps) |

## Build / Environment

```
docker pull php:8.4-fpm && docker run --rm php:8.4-fpm php -r 'echo PHP_VERSION;'   # 8.4.28
# Debian distro build:
docker run -it debian:trixie bash -c 'apt-get update && apt-get install -y php-fpm php-cli && php -v'
```

Escalated findings verified on default configuration unless stated: default Zend MM, FFI disabled, no `USE_ZEND_ALLOC` override, no ASan, opcache on (package default for FPM). E1 receipts at default ini and at explicit ini are both included.

## Disclaimer

This material is security research for **defensive** and **authorized** testing only. It is not permission or encouragement to attack systems you do not own or are not explicitly authorized to test.

- **PHP (the project / PHP Group / related foundations):** Findings, root-cause notes, status tables, and reproduction material are published so operators and maintainers can assess and patch. Do not use this material to compromise production PHP deployments without authorization.
- **CISA and similar government and standards bodies:** Treat this as coordinated, defensive research context intended to support vulnerability management and prioritization, not as a playbook for harmful activity against critical infrastructure or government systems.
- **Cyber threat intelligence firms and defenders:** Status tables and root-cause summaries are provided to support detection, prioritization, and remediation. Redistribution or reuse of this material for malicious operations is prohibited.

Unauthorized access to computer systems is illegal. The authors assume no liability for misuse.

## Credits

| Contribution | Credited to |
|-------------|-------------|
| All findings, verification, harnesses, receipts | [@acd421](https://x.com/acd421) |
| RCE research assistance, E2 user-filter UAF -> `write_property` hijack -> `system()` | [@GGSec](https://x.com/GGASecDe) |

## License

MIT (see LICENSE).