# Finding 2 — open_basedir relative-entry expansion escape

**Status:** 🔴 Live on 8.4.28 / 8.5.13 / 8.6.0 (CLI `-d` shape and real FPM pool).
**Class:** sandbox escape — `open_basedir` accepts relative entries that expand against the
daemon's startup cwd; for php-fpm that is `/`, so a relative entry is the whole filesystem.
**CVSS 3.1:** 7.7 (AV:N/AC:H/PR:L/UI:N/S:C/C:H/I:H/A:H — AC reflects the config precondition).

## Mechanism

- `open_basedir` entries are expanded once, at startup, against the process cwd at that
  moment. A relative entry (`../`) in the configuration therefore expands to "one above the
  startup cwd." php-fpm masters launch from `/` — one above `/` is `/` — so any pool whose
  basedir contains a relative entry has, in effect, no basedir at all.
- From a jailed CLI process, the same reachability appears with `-d open_basedir=jail:../`:
  the entry expands at parse, and climbing `chdir('..')` walks to `/` with every subsequent
  absolute read permitted (the `../` entry expands against the current cwd at check time
  once the entry is live in the compiled configuration).

## What does NOT work (tested, all three tips)

- Runtime `ini_set('open_basedir', $jail . ':../')` from a jailed process: the subset check
  passes (expansion is cwd-relative at set time) and the literal `../` is stored — but the
  runtime-stored literal does not grant escape; the climb stalls at the jail boundary.
- `getcwd()`-failure shapes (rmdir own cwd): all read/write/include escapes blocked.
- Plain deletion of the jail directory: Linux `getcwd()` follows renames; no failure window.

## Reproduce

CLI shape (matches the classic receipt):

```
mkdir -p /tmp/f49/jail/deep/down/here
echo MARKER > /tmp/f49/OUTSIDE.txt
php -n -d open_basedir=/tmp/f49/jail:../ poc/poc-f49d.php
# CWD0=/tmp/f49/jail/deep/down/here → 20× chdir('..') → CWD='/'
# OUTSIDE='MARKER'  PASSWD='root:x:0:0:root...'  include executed
```

Real FPM pool shape:

```
docker run -d --name bd -p 9001:9001 -v $PWD:/www php:8.4-fpm \
  php-fpm -F -y poc/fpm-bd.conf        # pool: php_admin_value[open_basedir] = /www:../
python3 ../crosspool/poc/fcgi_client.py 127.0.0.1 9001 /www/bdprobe.php
# PASSWD='root:x:0:0:root...'  HOSTNAME read — directly through the pool response
```

## Notes

- The fpm result requires no climb: the entry expanded to `/` at master startup, so
  absolute reads are simply permitted.
- A prior third-party "not reproduced" verdict on this class was a test artifact (missing
  prebuilt tree, wrong relative depth, missing `-n`); the faithful PoC shapes above are
  live on every tip. Reconstruction failures are not verdicts.

## Files

- `poc/poc-f49d.php` — CLI receipt shape (climb + outside read + passwd + include)
- `poc/fpm-bd.conf`, `poc/bdprobe.php` — real-pool demo
