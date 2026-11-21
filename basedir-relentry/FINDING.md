# Finding E2 (CVSS 7.9): open_basedir relative-entry expansion escape

**Status:** The relative entry itself must come from the imposed configuration
(`-d`, ini file, pool config, or `.user.ini`), so this is documented as a hardening
footgun rather than an escalated finding. What is new here, and receipted: when a
relative entry is active and the process cwd exceeds PATH_MAX, the engine enters a
**full-escape state** (not a one-level widen), and every attacker-only route to reach
that state was tested and is closed on these builds.

## The full-escape state (receipted, 8.4.28 + 8.5.13)

With a relative entry in the active basedir and `getcwd()` failing (cwd deeper than
PATH_MAX, inherited from the shell; PHP-side `chdir` cannot reach such depth because
the basedir check expands targets through a 4096 buffer):

- the startup parse cannot expand the relative entry, so it is stored **raw**
- at check time, relative traversal paths also stay unexpanded (`getcwd()` fails)
- the raw path prefix-matches the raw entry
- the kernel still resolves the relative path component-wise from the valid deep cwd

Result: `file_get_contents('../../...etc/passwd')` reads **`/etc/passwd` from outside
the jail**. Receipt: `receipts/basedir_deepcwd_receipts.log` (8.4.28, 8.5.13;
8.6.0 normalizes the inherited deep cwd to `/` and resists).

Threat model: a sandboxed CLI runner that imposes `open_basedir` containing a
relative entry and executes attacker-influenced code with an attacker-controlled
working directory (the attacker makes the tree deeper than PATH_MAX before the
runner starts). The relative entry still has to be in the imposed config, which is
why this is not escalated.

## Startup-expansion behavior (the ordinary footgun)

- `open_basedir` entries are expanded once at startup against the daemon's cwd.
  php-fpm masters launch from `/`, so a pool entry like `/www:../` expands to the
  filesystem root and the pool effectively has no basedir.
- From a jailed CLI process, `-d open_basedir=jail:../` with a shallow cwd expands
  `../` to the cwd parent (one level of widen), and the classic climb-to-root chain
  follows when the entry expands high enough.

## Per-dir ini nuance (`.user.ini`)

`.user.ini` parsing (CGI/FPM) stores `open_basedir` entries **without** expansion,
unlike the startup parse. A tenant-writable `.user.ini` can therefore put a raw
relative entry into the active basedir. Combined with the deep-cwd state this would
be a fully attacker-owned escape, but both SAPIs canonicalize the primary script
path through 4096 buffers, so a request can never reach a deep cwd:
- FPM: `File not found` for a script whose canonical path exceeds PATH_MAX, even
  via a chain of short relative symlinks that the shell resolves fine
- CGI (Debian php-cgi 8.4.28): `No input file specified` (same wall)

## What does NOT work (all tested, receipted or reproduced on all three tips)

- Runtime `ini_set('open_basedir', $jail . ':../')` with working getcwd: the subset
  check passes and the literal is stored, but enforcement expands it at set time;
  the climb stalls at the jail boundary.
- The same `ini_set` with failing getcwd (deep cwd): rejected outright.
- `getcwd()`-failure by deleting the cwd (rmdir): all escapes blocked, and file
  operations fail at the kernel anyway (deleted inode).
- PHP-side deep `chdir`: blocked at the 4096 expansion check in the basedir check.
- FPM/CGI requests into deep trees: canonical-path wall above.

## Hardening recommendations

- Reject or canonicalize relative `open_basedir` entries at ini parse time
  (covers startup, per-dir, and pool config).
- Make the basedir check fail closed on expansion failure at check time as well.
- Operators: never write relative entries; audit pool configs and `.user.ini`
  files (`grep -rE 'open_basedir.*\.\.' /etc/php*` and docroots).

## Reproduce (full-escape state)

```
mkdir -p /tmp/f49/jail && cd /tmp/f49/jail
C=$(printf 'a%.0s' $(seq 1 249))
for i in $(seq 1 18); do mkdir "$C"; cd "$C"; done     # cwd now > PATH_MAX
php -d open_basedir=/tmp/f49/jail:../ /path/to/poc-f49win3.php
# GETCWD_AT_START=false ... PASSWD='root:x:0:0:root:/root:/bin/bash...'  <- outside the jail
```

## Files

- `poc/poc-f49d.php` - CLI startup-expansion climb receipt shape
- `poc/poc-f49win3.php` - full-escape-state probe (deep cwd + raw entry)
- `poc/fpm-bd.conf`, `poc/bdprobe.php` - real-pool footgun demo
- `receipts/basedir_deepcwd_receipts.log` - full-escape receipts + 8.6 negative
