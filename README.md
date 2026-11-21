# PHP-PrettyHorriblePatches

Independently verified, receipt-backed security research against the current PHP tips: three live exploit classes on **8.4.28 / 8.5.13 / 8.6.0** (2026-11-19 security release), plus corpus re-validation. Every PoC here was rebuilt and re-run from scratch on 2026-11-20 — fresh containers, marker discipline (markers deleted between phases), fresh-compile controls. Where prior third-party claims disagreed with reproduction, the disagreement is in the notes.

**Authors:** [@acd421](https://x.com/acd421)
**Date:** 2026-11-20 (America/Chicago)
**Currency:** PHP **8.4.28 / 8.5.13 / 8.6.0** — php.net newest as of 2026-11-19; verified inside the images with `PHP_VERSION`, never trusted from tags. Also tested: Debian trixie `php-fpm8.4` package build `8.4.28-1` (built Nov 18 2026).

---

## Vulnerability Inventory

| # | Finding | CVSS | Attack Vector | Status | Folder |
|:-:|---------|:----:|--------------|:------:|--------|
| 1 | Cross-pool opcache function-dispatch confusion | **8.5** | Multi-tenant FPM: attacker pool compiles, victim pool executes, `disable_functions` differs | 🔴 Live RCE on **8.4.28 / 8.5.13 / 8.6.0** + Debian pkg — 12/12 marker-proven; realistic cPanel-style list → arbitrary file read | `crosspool/` |
| 2 | `open_basedir` relative-entry expansion escape | **7.7** | Any config with a relative basedir entry; php-fpm masters launch from `/` | 🔴 Full filesystem access, all three tips (CLI `-d` shape + real FPM pool demo) | `basedir-relentry/` |
| 3 | user-filter UAF → `write_property` hijack → `system()` | **7.2** | Attacker-controlled `php_user_filter` + `/proc/self/mem` (same-UID, stock) | 🔴 6/6 on stock release builds — `system()` marker ×3 tips, `abort()` exit 134 ×3 tips | `userfilter-uaf/` |
| 4 | Corpus re-validation (highlights) | varies | — | #1 Exception-trace confusion SIGSEGV **8.4.28 + 8.5.13** (clean 8.6.0); #6 quoted-printable OOB live **8.4.28 + 8.5.13**; #49 prior "dead" verdict retracted (see row 2) | `highs/` |

**CVSS 3.1 computed from formula; preconditions weigh on AC/PR as noted per finding.**

### Notes

- **1 — root cause (php-src 8.4.28):** `zend_disable_functions()` (`Zend/zend_API.c:3682`) deletes each entry then calls `zend_hash_rehash()` — the comment states it makes "all internal functions contiguous." Meanwhile opcache's SHM-shared call slots and the `ZEND_INIT_FCALL` type-specialized handler (`Zend/zend_vm_def.h:4034`) resolve calls via a **baked bucket index into the executing process's table with no name validation** (`EG(function_table)->arData[Z_EXTRA_P(...)]`). The plain handler's null check is a release-elided `ZEND_ASSERT` ("Function existence must be checked at compile time") — false under per-pool `disable_functions`. Result: pools whose tables differ call the wrong builtins, deterministically per (build, config, script).
- **1 — what it is NOT:** not a clean `disable_functions` bypass. The confused call lands on a function determined by table-shift arithmetic; composition of the disable list selects the target. `{exec, shell_exec}` → the `exec()` opline executes OS commands in the hardened pool (silent, marker-proven, 12/12). A 10-name cPanel-style list maps `passthru()` → `readfile()` (arbitrary file read through the victim pool's response, `/etc/passwd` demo).
- **1 — predictability:** the confusion map is enumerable offline. `get_defined_functions()['internal']` returns the table order; diffing pool A vs pool B lists gives every called-name → invoked-function pair for that deployment.
- **1 — controls:** identical pools and disabled-function-not-called cases execute cleanly (no confusion). Pool A fresh-compile fatals correctly. `ini_get('disable_functions')` is constant-folded at compile — a pool executing another pool's cached script prints the compiling pool's value; do not use it as evidence of configuration state.
- **2 — root cause:** `open_basedir` accepts relative entries and expands them once, against the daemon's startup cwd. php-fpm masters start at `/`, so a pool entry like `/www:../` expands to the filesystem root at startup — the jail never existed. The runtime `ini_set()` widen (jailed process appends `:../`, subset check passes because expansion is cwd-relative) is accepted and stores the literal, but does not itself yield escape on these builds — the live vector is the config-level relative entry.
- **3 — root cause:** `php_user_filter::filter()` calling `stream_filter_remove()` on itself frees the filter while `ext/standard/user_filters.c:225` still runs `zend_update_property_null` over the freed abstract after the callback returns. The wrapper reads the abstract slot as a direct `zend_object*`, loads `handlers` at +0x18, and dispatches `write_property` from the table (+0x28 on these builds). Full-bin string reclaim + `/proc/self/mem` slot patching (stock, same-UID) + a kept obj-host string gives `system(cmd)`.
- **3 — heap discipline matters:** patching only the abstract slot keeps the heap clean enough for `system()`'s internal allocations; a wider patch set leaves `abort()` working (no allocation) but `system()` dying. Both signatures are receipted.
- **4 — methodology note:** a prior "not reproduced" verdict on the open_basedir finding was retracted after running the original PoC shapes faithfully (prebuilt tree, exact flags). Reconstruction failures are not finding verdicts.

---

## Weapons

| Weapon | What it does |
|--------|-------------|
| `crosspool/poc/fcgi_client.py` | Dependency-free FastCGI responder client (stdout+stderr capture) |
| `crosspool/poc/run_multi1.sh` | Full marker-disciplined RCE matrix: B-warm → marker delete → A-fire |
| `crosspool/poc/oracle methodology` | `get_defined_functions()` diff → offline confusion-map computation |
| `basedir-relentry/poc/poc-f49d.php` | `-d open_basedir=jail:../` climb-to-root + outside reads/includes |
| `basedir-relentry/poc/fpm-bd.conf` + `bdprobe.php` | Real FPM pool with one relative entry → `/etc/passwd` through the response |
| `userfilter-uaf/poc/ip_noffi_v7.php` | End-to-end UAF → `write_property` → `system()` / `abort()`, pure PHP |

```
# finding 1 (RCE matrix, per tip)
docker run -d --name c2 -p 19001:9001 -p 19002:9002 -v $PWD:/www -v $PWD/conf:/cconf:ro \
  php:8.4-fpm php-fpm -F -y /cconf/fpm-multi1.conf -d opcache.enable=1 -d opcache.file_update_protection=0
bash run_multi1.sh 8.4   # expect: marker written by disabled pool A

# finding 2 (CLI shape)
php -n -d open_basedir=/tmp/f49/jail:../ poc-f49d.php   # CWD=/ + /etc/passwd read

# finding 3
docker run --rm -v $PWD:/h -e F7_TARGET=system -e F7_CMD="touch /tmp/PWNED" \
  php:8.6-cli php /h/ip_noffi_v7.php                     # marker + crash-after (command executed)
```

---

## Receipts

| Target | Chain | Receipt |
|--------|-------|---------|
| php:8.4-fpm (8.4.28, official) | crosspool `{exec,shell_exec}` | **marker by disabled pool A, 3/3** |
| php:8.5-fpm (8.5.13, official) | crosspool | **marker 3/3** |
| php:8.6-fpm (8.6.0, official) | crosspool | **marker 3/3** |
| Debian trixie php-fpm8.4 8.4.28-1 (distro build) | crosspool | **marker 3/3** |
| php:8.4-fpm, 10-name cPanel-style disable list | crosspool `passthru()` → `readfile()` | **full `/etc/passwd` dump through pool A response** |
| php:8.4/8.5/8.6-cli | basedir `jail:../` climb | **CWD=/, outside file + `/etc/passwd` + include executed** |
| php:8.4-fpm pool `open_basedir=/www:../` | basedir fpm shape | `/etc/passwd` + `/etc/hostname` through response |
| php:8.4/8.5/8.6-cli (stock, default MM, FFI off) | userfilter UAF → `system()` | **marker 3/3** |
| php:8.4/8.5/8.6-cli | userfilter UAF → `abort()` | **exit 134, 3/3** |
| php:8.4/8.5-cli | #1 Exception-trace confusion | SIGSEGV exit 139 (heap addresses as frame indexes) |
| php:8.4/8.5-cli | #6 quoted-printable OOB | 16-byte input → 15-byte output, trailing SP deleted |

---

## Build / Environment

```
docker pull php:8.4-fpm && docker run --rm php:8.4-fpm php -r 'echo PHP_VERSION;'   # 8.4.28
# Debian distro build:
docker run -it debian:trixie bash -c 'apt-get update && apt-get install -y php-fpm php-cli && php -v'
```

All findings verified on default configuration unless stated: default Zend MM, FFI disabled, no `USE_ZEND_ALLOC` override, no ASan, opcache on (package default for FPM).

## Disclaimer

Security research for defensive and authorized testing only, published so operators and maintainers can assess and patch. Not permission to attack systems you do not own or are not authorized to test.

## License

MIT.
