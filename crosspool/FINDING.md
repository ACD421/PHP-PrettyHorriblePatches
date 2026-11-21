# Finding E2 (CVSS 7.9), Cross-pool opcache function-dispatch confusion

**Status:** 🔴 Live on 8.4.28 / 8.5.13 / 8.6.0 (official images) and Debian `php-fpm8.4` 8.4.28-1.
**Class:** incorrect function dispatch across differently-configured FPM pools → `disable_functions` bypass (OS command execution) and arbitrary builtin invocation.
**CVSS 3.1:** 7.9 (AV:N/AC:H/PR:L/UI:N/S:C/C:H/I:H/A:H, AC reflects the multi-tenant precondition). Computed from formula.

## Preconditions

- php-fpm, ≥2 pools under one master (shared opcache SHM, automatic).
- Pools differ in `disable_functions` (standard shared-hosting hardening).
- A script path compiled in the attacker's pool is executed by the victim pool
  (shared include directories, `open_basedir` jail:shared layouts, multi-tenant panels).

## Mechanism

1. `zend_disable_functions()` (`Zend/zend_API.c:3682`) deletes each disabled entry, then
   `zend_hash_rehash()` compacts the function table, every function after a deleted one
   moves to a different bucket.
2. The `ZEND_INIT_FCALL` type-specialized handler (`Zend/zend_vm_def.h:4034`) resolves
   internal calls via a bucket index baked at compile time in the *compiling* pool's table:

   ```c
   fbc = CACHED_PTR(opline->result.num);
   if (UNEXPECTED(fbc == NULL)) {
       fbc = Z_PTR(EG(function_table)->arData[Z_EXTRA_P(RT_CONSTANT(opline, op->op2))].val);
   }
   ```

   No name validation. The plain handler's null check is a release-elided `ZEND_ASSERT`
   ("Function existence must be checked at compile time"), false under per-pool disables.
3. A script compiled in pool B (no disables) executes in pool A (hardened): call slots and
   baked indexes point into A's rehashed table → wrong builtins invoked with the script's
   (attacker-controlled) arguments. Deterministic per (build, config, script).
4. Complex/dynamic scripts can SIGSEGV instead (the elided-assert null deref), a separate DoS
   signature of the same defect.

## Observed dispatch maps (docker official, all three tips identical)

| Pool A `disable_functions` | Script calls | Invoked in pool A | Effect |
|---|---|---|---|
| `exec` | `exec()` | harmless (returns `""`) | none on this layout |
| `exec, shell_exec` | `exec()` | **command-executing handler** | **OS command runs in pool A (marker, silent), 12/12** |
| `exec, shell_exec` | `shell_exec()` | `flock` | ArgumentCountError |
| 10-name cPanel-style list | `passthru()` | `readfile` | **arbitrary file read through pool A response** |
| same | `system()` | `pclose` | none |
| `exec, shell_exec, system, passthru, proc_open, popen, pcntl_exec, curl_exec` | `shell_exec()` | `readfile` | arbitrary file read |

The map is enumerable offline: `get_defined_functions()['internal']` (table order) from both
pools; the same index in both lists gives called-name → invoked-function.

## Reproduce (marker-disciplined)

```
docker pull php:8.4-fpm && docker run --rm php:8.4-fpm php -r 'echo PHP_VERSION;'   # 8.4.28
cd poc/
docker run -d --name c2m -p 19001:9001 -p 19002:9002 \
  -v $PWD/www:/www -v $PWD/conf:/cconf:ro \
  php:8.4-fpm php-fpm -F -y /cconf/fpm-multi1.conf \
  -d opcache.enable=1 -d opcache.file_update_protection=0
python3 fcgi_client.py 127.0.0.1 19002 /www/gt_exec.php WARM   # pool B compiles
docker exec c2m rm -f /www/mk_exec.log                          # marker deleted
python3 fcgi_client.py 127.0.0.1 19001 /www/gt_exec.php FIRE    # pool A (disabled) fires
docker exec c2m cat /www/mk_exec.log                            # GT3_EXEC → RCE proven
```

Controls (same harness): identical pools → clean execution; disabled-function-not-called →
clean; pool A fresh-compile → correct `Call to undefined function` fatal.

## Caveats

- `ini_get('disable_functions')` is constant-folded at compile time, a pool executing
  another pool's cached script prints the *compiling* pool's value. Do not use it as
  evidence of the executing pool's configuration.
- The invoked-target arithmetic depends on build layout and the disable-list composition;
  enumerate per deployment with the oracle above rather than assuming a fixed map.

## Files

- `poc/fcgi_client.py`, FastCGI responder client (stdout+stderr capture)
- `poc/conf/fpm-multi1.conf`, the RCE-proven `{exec, shell_exec}` composition
- `poc/conf/fpm-whm.conf`, realistic 10-name cPanel-style list (file-read demo)
- `poc/run_multi1.sh`, 3×3 tip matrix + Debian package build (12/12 marker)
- `receipts/multi1_matrix.log`, 12/12
- `receipts/gt3_8.{4,5,6}.log`, per-tip first-call family maps
