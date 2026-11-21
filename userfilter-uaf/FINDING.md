# Finding E3 (CVSS 7.5), user-filter UAF → write_property hijack → system()

**Status:** 🔴 6/6 on stock release builds, `system()` marker on 8.4.28 / 8.5.13 / 8.6.0
(3/3 each), `abort()` exit 134 (3/3). Default Zend MM, FFI disabled, no `USE_ZEND_ALLOC`
override, no ASan. Pure PHP, end to end.
**Class:** use-after-free → arbitrary handler dispatch → arbitrary code execution.
**CVSS 3.1:** 7.5 (AV:N/AC:H/PR:L/UI:N/S:U/C:H/I:H/A:H, AC reflects the code-execution precondition). Computed from formula.

## Preconditions

- Attacker-controlled PHP that can register a `php_user_filter` subclass (any
  upload/RFI/code-exec foothold in a web app, or local code).
- `/proc/self/mem` + `/proc/self/maps` readable/writable (stock Linux, same-UID, no
  privileges; blocked only by `open_basedir`/`disable_functions`).
- `nm -D` on libc for the `system`/`abort` offset (or hardcode per target libc build to
  remove the shell dependency).

## Mechanism (every hop gdb-verified on stock 8.4.28/8.5.13/8.6.0)

1. `php_user_filter::filter()` calls `stream_filter_remove()` on itself. The filter and
   its abstract are freed, but `ext/standard/user_filters.c:225` runs
   `zend_update_property_null` over the freed abstract *after the callback returns*
   (the `->stream` property cleanup).
2. The wrapper reads the freed chunk's abstract slot (`*(chunk+8)`) as a direct
   `zend_object*`, no zval hop on these builds.
3. Full-bin string spray (fresh kept strings across every small size class, magic-tagged)
   reclaims the freed chunk regardless of which bin it lands in.
4. `/proc/self/mem` patches the abstract slot to point at an obj-host string: a kept
   string carrying the command at `data+0` and the handlers-table pointer at `data+0x18`,
   both placed at creation so they live in stable memory through the call.
5. The handlers table (in a beaconed big string) has `write_property` (+0x28 on these
   builds; +0x20/+0x30 also patched for path variance) = libc `system`.
6. The wrapper's property-null write becomes `system(obj)` → the command at the object
   base executes. `abort()` variant = IP-control signature (exit 134, no args needed).

## Heap discipline (why earlier variants failed)

- Patch **only** the abstract slot. Wider patch sets corrupt kept-string GC headers;
  `abort()` survives a corrupted heap (it never allocates), but `system()` dies inside its
  own allocations. Both signatures are useful: `abort` = control-flow proof, `system` = RCE.
- Pre-cache `/proc/self/maps` regions before the callback, the maps *line strings* are
  themselves same-bin allocations and steal the freed chunk mid-scan.
- Scan needles must not match their own needle strings (use `[ptr][magic]` context).
- The command must live in a kept string; beaconed scratch regions recycle.

## Reproduce

```
docker run --rm -v $PWD:/h -e F7_TARGET=system -e F7_CMD="touch /tmp/PWNED" \
  php:8.6-cli php /h/ip_noffi_v7.php
# ... RESUME → process dies post-call, /tmp/PWNED created → command executed

docker run --rm -v $PWD:/h -e F7_TARGET=abort php:8.4-cli php /h/ip_noffi_v7.php
# PHP_EXIT=134 (SIGABRT through the forged write_property)
```

## Files

- `poc/ip_noffi_v7.php`, end-to-end PoC (system/abort via `F7_TARGET`, command via `F7_CMD`)
- `receipts/f7_FINAL_receipts.log`, 6/6 (marker ×3 + exit-134 ×3)
