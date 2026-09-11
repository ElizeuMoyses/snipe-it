<?php
$files = [
    'contracts/general' => [
        '/var/www/html/resources/lang/en-US/admin/contracts/general.php',
        '/var/www/html/resources/lang/pt-BR/admin/contracts/general.php',
    ],
    'contracts/table' => [
        '/var/www/html/resources/lang/en-US/admin/contracts/table.php',
        '/var/www/html/resources/lang/pt-BR/admin/contracts/table.php',
    ],
    'csl/general' => [
        '/var/www/html/resources/lang/en-US/admin/contract_status_labels/general.php',
        '/var/www/html/resources/lang/pt-BR/admin/contract_status_labels/general.php',
    ],
    'csl/table' => [
        '/var/www/html/resources/lang/en-US/admin/contract_status_labels/table.php',
        '/var/www/html/resources/lang/pt-BR/admin/contract_status_labels/table.php',
    ],
];

foreach ($files as $name => [$enFile, $ptFile]) {
    $en = include $enFile;
    $pt = include $ptFile;
    $missing = array_diff_key($en, $pt);
    $extra = array_diff_key($pt, $en);
    echo "$name => EN:" . count($en) . " PT:" . count($pt);
    echo " Missing:" . (empty($missing) ? 'NONE' : implode(',', array_keys($missing)));
    echo " Extra:" . (empty($extra) ? 'NONE' : implode(',', array_keys($extra)));
    echo PHP_EOL;
}

// message.php has nested arrays, compare recursively
function flattenKeys($arr, $prefix = '') {
    $keys = [];
    foreach ($arr as $k => $v) {
        $full = $prefix ? "$prefix.$k" : $k;
        if (is_array($v)) {
            $keys = array_merge($keys, flattenKeys($v, $full));
        } else {
            $keys[$full] = $v;
        }
    }
    return $keys;
}

$msgFiles = [
    'contracts/message' => [
        '/var/www/html/resources/lang/en-US/admin/contracts/message.php',
        '/var/www/html/resources/lang/pt-BR/admin/contracts/message.php',
    ],
    'csl/message' => [
        '/var/www/html/resources/lang/en-US/admin/contract_status_labels/message.php',
        '/var/www/html/resources/lang/pt-BR/admin/contract_status_labels/message.php',
    ],
];

foreach ($msgFiles as $name => [$enFile, $ptFile]) {
    $en = flattenKeys(include $enFile);
    $pt = flattenKeys(include $ptFile);
    $missing = array_diff_key($en, $pt);
    $extra = array_diff_key($pt, $en);
    echo "$name => EN:" . count($en) . " PT:" . count($pt);
    echo " Missing:" . (empty($missing) ? 'NONE' : implode(',', array_keys($missing)));
    echo " Extra:" . (empty($extra) ? 'NONE' : implode(',', array_keys($extra)));
    echo PHP_EOL;
}

echo PHP_EOL . "=== VALIDATION COMPLETE ===" . PHP_EOL;
