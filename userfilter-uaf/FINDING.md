# Finding E2 (CVSS 7.5): user-filter UAF to write_property hijack to system()

**Status:** 6/6 on stock release builds, run under a full cPanel-style
`disable_functions` list (`exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source`):
`system()` marker on 8.4.28 / 8.5.13 / 8.6.0 (3/3 each) and `abort()` exit 134 (3/3).
Default Zend MM, FFI disabled, no `USE_ZEND_ALLOC` override, no ASan, no shell calls
anywhere in the PoC. Pure PHP end to end.
**Class:** use-after-free -> arbitrary handler dispatch -> arbitrary code execution.
**CVSS 3.1:** 7.5, `AV:N/AC:H/PR:L/UI:N/S:U/C:H/I:H/A:H`, computed from formula.

## Preconditions

- Attacker-controlled PHP that can register a `php_user_filter` subclass (any
  upload/RFI/code-exec foothold in a web app, or local code).
- `disable_functions` is the hardening being defeated: the PoC runs with the full
  cPanel-style list above and still reaches libc `system()`.
- `/proc/self/maps` + `/proc/self/mem` accessible (stock Linux, same-UID, no
  privileges). Note: `open_basedir` blocks these paths, so the PoC as shipped
  defeats `disable_functions` but not `open_basedir`.

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
- `receipts/f7v8_hardened.log` - 6/6 under the full disable_functions list
  (marker x3 + exit-134 x3, true exit codes)
