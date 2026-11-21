<?php
echo "PHP " . PHP_VERSION . "\n";

$date = "2020-01-01 00:00:00.000000";
$off = 'O:8:"DateTime":3:{s:4:"date";s:26:"' . $date . '";s:13:"timezone_type";i:1;s:8:"timezone";s:7:"+2 days";}';
$ctl = 'O:8:"DateTime":3:{s:4:"date";s:26:"' . $date . '";s:13:"timezone_type";i:1;s:8:"timezone";s:6:"+00:00";}';

$bad = unserialize($off);
$ok = unserialize($ctl);
echo "unserialize tz +2 days => " . $bad->format("Y-m-d H:i:s T") . " ts=" . $bad->getTimestamp() . "\n";
echo "unserialize tz +00:00 => " . $ok->format("Y-m-d H:i:s T") . " ts=" . $ok->getTimestamp() . "\n";
echo "delta_sec=" . ($bad->getTimestamp() - $ok->getTimestamp()) . "\n";

$abbr = DateTime::__set_state([
    "date" => $date,
    "timezone_type" => 2,
    "timezone" => "+1 day",
]);
echo "set_state abbr +1 day => " . $abbr->format("Y-m-d H:i:s T") . " ts=" . $abbr->getTimestamp() . "\n";

$d = new DateTime("2000-01-01 00:00:00 UTC");
$d->setDate(PHP_INT_MAX, 1, 1);
echo "maxyear Y=" . $d->format("Y") . " U=" . $d->format("U") . "\n";

$g = getdate(PHP_INT_MIN);
echo "getdate INT_MIN mon=" . $g["mon"] . " month=" . $g["month"] . " year=" . $g["year"] . "\n";
echo "date F INT_MIN=" . date("F", PHP_INT_MIN) . "\n";

$c = date_create("2020-01-01 00:00:00 +999999");
echo "off999999 " . ($c ? $c->format("c") . " getOffset=" . $c->getOffset() : "false") . "\n";

echo "DONE\n";
