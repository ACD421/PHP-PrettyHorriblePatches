<?php
$s = function_exists('opcache_get_status') ? opcache_get_status(false) : null;
if (!$s) { echo "no-status\n"; return; }
echo "enabled=" . (int)$s['opcache_enabled']
   . " scripts=" . $s['opcache_statistics']['num_cached_scripts']
   . " hits=" . $s['opcache_statistics']['hits']
   . " misses=" . $s['opcache_statistics']['misses'] . "\n";
