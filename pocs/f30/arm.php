<?php
header('Content-Type: text/plain; charset=UTF-8');
echo 'ARMED met=' . ini_get('max_execution_time') . ' mit=' . ini_get('max_input_time') . "\n";
