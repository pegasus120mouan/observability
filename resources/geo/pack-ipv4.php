<?php

declare(strict_types=1);

$source = __DIR__.DIRECTORY_SEPARATOR.'ipv4-country-num.csv';
$target = __DIR__.DIRECTORY_SEPARATOR.'ipv4-country-16.bin';
$handle = fopen($source, 'rb');

if ($handle === false) {
    fwrite(STDERR, "Cannot read {$source}\n");
    exit(1);
}

$table = array_fill(0, 65536, "\0\0");

while (($line = fgets($handle)) !== false) {
    $line = trim($line);

    if ($line === '' || ! preg_match('/^(\d+),(\d+),([A-Z]{2})$/', $line, $matches)) {
        continue;
    }

    $from = intdiv((int) $matches[1], 65536);
    $to = intdiv((int) $matches[2], 65536);
    $country = $matches[3];

    for ($prefix = $from; $prefix <= $to; $prefix++) {
        $table[$prefix] = $country;
    }
}

fclose($handle);

file_put_contents($target, implode('', $table));

echo 'bytes='.filesize($target).PHP_EOL;
