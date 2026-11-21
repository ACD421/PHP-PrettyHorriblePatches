# Configuration footgun: open_basedir relative entries (not an escalated finding)

**Status:** Live on 8.4.28 / 8.5.13 / 8.6.0 (CLI `-d` shape and real FPM pool),
but the live vector requires an operator to have written a relative entry
(for example `../`) into `open_basedir`. PHP documents basedir entries as absolute
paths; a relative entry is operator error that the engine tolerates. We therefore
document this as a hardening footgun, not as an escalated vulnerability, and no
CVSS is claimed for it.

## Behavior

- `open_basedir` entries are expanded once, at startup, against the process cwd at
  that moment. php-fpm masters launch from `/`, so a pool entry like `/www:../`
  expands to the filesystem root at startup, and the pool effectively has no basedir:
  absolute reads of `/etc/passwd` succeed directly through the response.
- From a jailed CLI process the same reachability appears with
  `-d open_basedir=jail:../`: the entry expands at parse, and climbing `chdir('..')`
  walks to `/` with every subsequent absolute read permitted.

## What does NOT work (tested, all three tips)

- Runtime `ini_set('open_basedir', $jail . ':../')` from a jailed process: the
  subset check passes (expansion is cwd-relative at set time) and the literal
  `../` is stored, but the runtime-stored literal does not grant escape; the
  climb stalls at the jail boundary.
- `getcwd()`-failure shapes (rmdir own cwd): all read/write/include escapes blocked.
- Plain deletion of the jail directory: Linux `getcwd()` follows renames; no
  failure window.

## Hardening recommendations

- Reject or canonicalize relative `open_basedir` entries at ini parse time
  (engine-side fix).
- Operators: never write relative entries; audit pool configs for them
  (`grep -r 'open_basedir.*\.\./' /etc/php*`).

## Reproduce

```
mkdir -p /tmp/f49/jail/deep/down/here
echo MARKER > /tmp/f49/OUTSIDE.txt
php -n -d open_basedir=/tmp/f49/jail:../ poc/poc-f49d.php
# CWD0=/tmp/f49/jail/deep/down/here -> 20x chdir('..') -> CWD='/'
# OUTSIDE='MARKER'  PASSWD='root:x:0:0:root...'  include executed
```

FPM pool shape (receipt in `crosspool/receipts/e1_default_whm.log` context):

```
docker run -d --name bd -p 9001:9001 -v $PWD:/www php:8.4-fpm \
  php-fpm -F -y poc/fpm-bd.conf        # pool: php_admin_value[open_basedir] = /www:../
python3 ../crosspool/poc/fcgi_client.py 127.0.0.1 9001 /www/bdprobe.php
# PASSWD='root:x:0:0:root...'  HOSTNAME read - directly through the pool response
```

## Files

- `poc/poc-f49d.php` - CLI receipt shape (climb + outside read + passwd + include)
- `poc/fpm-bd.conf`, `poc/bdprobe.php` - real-pool demo
