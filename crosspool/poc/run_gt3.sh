#!/bin/bash
# Decisive: exec-family as FIRST call, clean marker discipline (rm between B-warm and A-fire).
# usage: run_gt3.sh <tag>
set -u
TAG=${1:?tag}
H=/mnt/c/Users/andre/php-verify
LOG="$H/logs/gt3_${TAG}.log"
mkdir -p "$H/logs"
NAME=g3-$(echo "$TAG" | tr -d '.')

docker rm -f "$NAME" >/dev/null 2>&1
docker run -d --name "$NAME" -p 19001:9001 -p 19002:9002 \
  -v "$H/www":/www -v "$H/conf":/cconf:ro \
  php:${TAG}-fpm php-fpm -F -y /cconf/fpm.conf \
  -d opcache.enable=1 -d opcache.file_update_protection=0 >/dev/null || { echo "RUN FAIL"; exit 1; }
ok=""
for i in $(seq 1 15); do
  out=$(python3 "$H/fcgi_client.py" 127.0.0.1 19002 /www/ping.php 2>/dev/null)
  echo "$out" | grep -q pong && { ok=1; break; }
  sleep 1
done
[ -z "$ok" ] && { echo "== $TAG UNREACHABLE"; docker rm -f "$NAME" >/dev/null; exit 1; }
V=$(docker exec "$NAME" php -r 'echo PHP_VERSION;')

{
echo "=== $TAG (real $V) exec-family first-call matrix $(date) ==="
for s in exec shell system passthru; do
  echo "--- [$s] B warm:"
  python3 "$H/fcgi_client.py" 127.0.0.1 19002 /www/gt_$s.php 2>/dev/null | sed -n '/^Content-type/,$p' | tail -n +2
  docker exec "$NAME" rm -f /www/mk_$s.log
  echo "--- [$s] A fire (marker cleared):"
  python3 "$H/fcgi_client.py" 127.0.0.1 19001 /www/gt_$s.php 2>/dev/null | sed -n '/^Content-type/,$p' | tail -n +2
  if docker exec "$NAME" test -f /www/mk_$s.log; then
    echo "--- [$s] *** VERDICT: RAN IN DISABLED POOL ***"
  else
    echo "--- [$s] verdict: no marker (blocked/crashed)"
  fi
done
echo "--- crash log:"
docker logs "$NAME" 2>&1 | grep -E 'signal' | tail -6
} | tee "$LOG"
docker rm -f "$NAME" >/dev/null 2>&1
