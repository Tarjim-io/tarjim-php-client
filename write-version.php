#!/usr/bin/env php
<?php

// Exit on any error
try {
    // Try to get the latest Git tag (e.g., v1.3.0)
    $version = trim(shell_exec('git describe --tags --abbrev=0 2>&1'));

    if (!$version || str_starts_with($version, 'fatal')) {
        $version = 'unknown';
    }

    file_put_contents(__DIR__ . '/VERSION', $version);
    echo "✔️ Version written to VERSION file: $version\n";
} catch (Exception $e) {
    echo "❌ Failed to write version file: " . $e->getMessage() . "\n";
    exit(1);
}
