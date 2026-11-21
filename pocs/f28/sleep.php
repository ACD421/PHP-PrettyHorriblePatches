<?php
$n = isset($_GET["sleep"]) ? (int) $_GET["sleep"] : 2;
sleep($n);
echo "slept\n";
