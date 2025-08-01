#!/usr/bin/env php
<?php

// usage: php write-version.php v1.3.0

$logFile = __DIR__ . '/version-script-debug.log';

// Get version from command-line argument
$version = $argv[1] ?? null;

if (!$version) {
    $msg = "❌ No version provided. Usage: php write-version.php v1.3.0";
    echo $msg . PHP_EOL;
    file_put_contents($logFile, date('Y-m-d H:i:s') . " $msg\n", FILE_APPEND);
    exit(1);
}

// Optional: clean up "v" prefix if you only want "1.3.0"
$version = ltrim($version, 'v');

// Write version to file
file_put_contents(__DIR__ . '/VERSION', $version);

// Log and confirm
file_put_contents($logFile, date('Y-m-d H:i:s') . " VERSION set to $version\n", FILE_APPEND);
echo "✔️ VERSION file written with: $version\n";
