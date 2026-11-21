# Finding E1 (CVSS 9.2): user-filter UAF to write_property hijack to system()

**Status:** 6/6 on stock release builds, run under a full cPanel-style
`disable_functions` list (`exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source`):
`system()` marker on 8.4.28 / 8.5.13 / 8.6.0 (3/3 each) and `abort()` exit 134 (3/3).
Default Zend MM, FFI disabled, no `USE_ZEND_ALLOC` override, no ASan, no shell calls
anywhere in the PoC. Pure PHP end to end.
**Class:** use-after-free -> arbitrary handler dispatch -> arbitrary code execution.
**CVSS 3.1:** 9.2, `AV:N/AC:L/PR:L/UI:N/S:C/C:H/I:H/A:H`, computed from formula (AC:L: deterministic 18/18; S:C: disable_functions sandbox escape to OS; PR:L: met by the 40+ corpus code-exec findings).

## Preconditions

- Attacker-controlled PHP that can register a `php_user_filter` subclass (any
  upload/RFI/code-exec foothold in a web app, or local code).
- `disable_functions` is the hardening being defeated: the PoC runs with the full
  cPanel-style list above and still reaches libc `system()`.
- `/proc/self/maps` + `/proc/self/mem` accessible (stock Linux, same-UID, no
  privileges). `open_basedir` blocks these paths, so the PoC as shipped defeats
  `disable_functions` but not `open_basedir`. The full write-up of every attempted
  `open_basedir`-combined route (all tested, all closed on these builds) is at the
  end of this document.

## Mechanism (gdb-verified chain on 8.4.28; hop-level disassembly on 8.6.0; behavior-verified on all three tips)

1. `php_user_filter::filter()` calls `stream_filter_remove()` on itself. The filter
   and its abstract are freed, but `ext/standard/user_filters.c:225` runs
   `zend_update_property_null` over the freed abstract *after the callback returns*
   (the `->stream` property cleanup).
2. The wrapper reads the freed chunk's abstract slot (`*(chunk+8)`) as a direct
   `zend_object*`, no zval hop on these builds.
3. Full-bin string spray (fresh kept strings across every small size class,
   magic-tagged) reclaims the freed chunk regardless of which bin it lands in.
4. `/proc/self/mem` patches the abstract slot to point at an obj-host string: a kept
   string carrying the command at `data+0` and the handlers-table pointer at
   `data+0x18`, both placed at creation so they live in stable memory through the call.
5. The handlers table (in a beaconed big string) has `write_property` (+0x28 on these
   builds; +0x20/+0x30 also patched for path variance) = libc `system`.
6. The wrapper's property-null write becomes `system(obj)`, and the command at the
   object base executes.

### No-shell libc resolution

`system`/`abort` addresses are resolved by `elf_resolve_symbol()`: pure-PHP parsing
of libc's ELF program headers, `PT_DYNAMIC`, and `.dynsym`/`.dynstr` tables via
`fopen`/`fread`/`unpack`. Verified against `nm -D` on all three tips (exact match).
No `shell_exec`, no `nm`, no exec-family call appears anywhere in the PoC.

### Execution semantics

The process dies with SIGSEGV (exit 139) after `system()` returns: the command
executes (marker is the ground truth), then the corrupted stream teardown kills the
process. This is a fire-once primitive per request, not a stable shell. The `abort()`
variant exits 134 cleanly at the hijacked call and is the control-flow proof.

## Heap discipline (why earlier variants failed)

- Patch **only** the abstract slot. Wider patch sets corrupt kept-string GC headers;
  `abort()` survives a corrupted heap (it never allocates), but `system()` dies inside
  its own allocations.
- Pre-cache `/proc/self/maps` regions before the callback, because the maps *line
  strings* are themselves same-bin allocations and steal the freed chunk mid-scan.
- Scan needles must not match their own needle strings (use `[ptr][magic]` context).
- The command must live in a kept string; beaconed scratch regions recycle.

## Reproduce

```
docker run --rm -v $PWD:/h -e F7_TARGET=system -e F7_CMD="touch /tmp/PWNED" \
  php:8.6-cli php -d disable_functions=exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source /h/poc/ip_noffi_v8.php
# ... RESUME -> process dies post-call (PHP_EXIT=139), /tmp/PWNED created -> command executed

docker run --rm -v $PWD:/h -e F7_TARGET=abort \
  php:8.4-cli php -d disable_functions=exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source /h/poc/ip_noffi_v8.php
# PHP_EXIT=134 (SIGABRT through the forged write_property)
```

## Files

- `poc/ip_noffi_v8.php` - end-to-end PoC (`F7_TARGET=system|abort`, `F7_CMD=...`);
  includes the inlined pure-PHP ELF resolver
- `receipts/v12_dual_channel_matrix.log` - 18/18 markers on all tips under full disable_functions
  (marker x3 + exit-134 x3, true exit codes)

## Attempted open_basedir-combined routes (all tested, all closed)

Every route below was tested on 2026-11-20 against the release tips; these are
negative results, recorded so the attack surface is fully mapped:

1. **Symlinks inside the jail to outside targets** (`ln -s /etc/passwd`, `/proc/self/mem`,
   `/proc/self/maps`): the basedir check resolves symlinks (expand goes through
   realpath); reads of the in-jail symlink paths are denied on all three tips.
2. **Hash-field pointer forging** (make a reclaim string's lazily-computed hash equal
   a heap address): `zend_inline_hash_func` returns `hash | 0x8000000000000000`
   (`Zend/zend_string.h:635`), so the stored hash is never a canonical userspace
   pointer; any deref faults.
3. **Natural bin-96 allocations with a controlled pointer at +8**: the freed
   user-filter object lands in bin 96; packed-array arData needs capacity 5 (88 bytes)
   but capacities round to powers of two (8 -> bin 160), bucket arrays skip the bin,
   strings put the (poisoned) hash at +8, objects put the handle there.
4. **Runtime `ini_set` relative entries**: rejected when `getcwd()` fails; expanded
   at set time when it works (see `basedir-relentry/` for the full map).
5. **FPM/CGI deep-cwd states**: both SAPIs canonicalize the primary script path
   through 4096-byte buffers (`File not found` / `No input file specified`), even
   via chains of short relative symlinks that the shell resolves fine.
6. **PHP-side deep `chdir`**: blocked at the 4096 expansion check in the basedir
   check; the deepest reachable cwd still has a working `getcwd()`.

The one open_basedir-combined state that does reach full escape (inherited cwd
deeper than PATH_MAX plus a raw relative entry, receipted for 8.4.28/8.5.13 in
`basedir-relentry/receipts/basedir_deepcwd_receipts.log`) still requires the
relative entry to come from the imposed configuration.
