#!/bin/bash
# multi1 composition (exec,shell_exec disabled) across tips + Debian pkg, 3x each.
# usage: run_multi1.sh
set -u
H=/mnt/c/Users/andre/php-verify
LOG="$H/logs/multi1_matrix.log"
mkdir -p "$H/logs"

start_docker() { # $1 name, $2 tag
  docker rm -f "$1" >/dev/null 2>&1
  docker run -d --name "$1" -p 19001:9001 -p 19002:9002 \
    -v "$H/www":/www -v "$H/conf":/cconf:ro \
    php:${2}-fpm php-fpm -F -y /cconf/fpm-multi1.conf \
    -d opcache.enable=1 -d opcache.file_update_protection=0 >/dev/null
}
start_deb() { # $1 name
  docker rm -f "$1" >/dev/null 2>&1
  docker run -d --name "$1" -p 19001:9001 -p 19002:9002 \
    -v "$H/www":/www -v "$H/conf":/cconf:ro \
    debian:trixie sleep infinity >/dev/null
  docker exec "$1" bash -c 'apt-get update -qq >/dev/null 2>&1; apt-get install -y -qq php-fpm php-cli >/dev/null 2>&1'
  docker exec "$1" bash -c 'cat > /tmp/fpm.conf <<EOF
[global]
error_log = /proc/self/fd/2
daemonize = no

[poolA]
user = www-data
group = www-data
listen = 0.0.0.0:9001
pm = static
pm.max_children = 2
php_admin_value[disable_functions] = exec, shell_exec
php_admin_value[display_errors] = On

[poolB]
user = www-data
group = www-data
listen = 0.0.0.0:9002
pm = static
pm.max_children = 2
php_admin_value[display_errors] = On
EOF'
  docker exec -d "$1" bash -c '/usr/sbin/php-fpm8.4 -F -y /tmp/fpm.conf -c /cconf/deb.ini 2>/tmp/fpm.err'
}
waitup() {
  local NAME=$1
  for i in $(seq 1 20); do
    out=$(python3 "$H/fcgi_client.py" 127.0.0.1 19002 /www/ping.php 2>/dev/null)
    echo "$out" | grep -q pong && return 0
    sleep 1
  done
  return 1
}
fireA() { python3 "$H/fcgi_client.py" 127.0.0.1 19001 "$1" 2>/dev/null | sed -n '/^Content-type/,$p' | tail -n +2; }
fireB() { python3 "$H/fcgi_client.py" 127.0.0.1 19002 "$1" 2>/dev/null | sed -n '/^Content-type/,$p' | tail -n +2; }

test_rce() { # $1 label, $2 container name
  local LBL=$1 NAME=$2
  fireB /www/gt_exec.php >/dev/null 2>&1
  docker exec "$NAME" rm -f /www/mk_exec.log
  fireA /www/gt_exec.php >/dev/null 2>&1
  if docker exec "$NAME" test -f /www/mk_exec.log; then
    echo "$LBL: *** RCE — marker written by disabled pool A ***"
  else
    echo "$LBL: no marker (A blocked/crashed/benign-bind)"
  fi
}

{
for round in 1 2 3; do
  for TAG in 8.4 8.5 8.6; do
    NAME=m1-$(echo "$TAG" | tr -d '.')-$round
    start_docker "$NAME" "$TAG"
    waitup "$NAME" || { echo "$TAG r$round: UNREACHABLE"; continue; }
    test_rce "docker $TAG r$round" "$NAME"
    docker rm -f "$NAME" >/dev/null 2>&1
  done
done
for round in 1 2 3; do
  NAME=m1deb-$round
  start_deb "$NAME"
  waitup "$NAME" || { echo "deb r$round: UNREACHABLE"; docker exec "$NAME" bash -c 'tail -5 /tmp/fpm.err' 2>/dev/null; docker rm -f "$NAME" >/dev/null; continue; }
  V=$(docker exec "$NAME" php -r 'echo PHP_VERSION;' 2>/dev/null)
  test_rce "debian-pkg($V) r$round" "$NAME"
  docker rm -f "$NAME" >/dev/null 2>&1
done
} | tee "$LOG"
