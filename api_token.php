<?php

/**
 * GoldApp management API token storage.
 *
 * API tokens are generated with high entropy and only their SHA-256 digest is
 * stored on disk. Legacy plaintext token files are migrated after the first
 * successful authentication so existing API clients do not need to change.
 */

function goldappApiTokenFile(): string
{
    $override = getenv('GOLDAPP_API_TOKEN_FILE');
    if ($override !== false && trim((string) $override) !== '') {
        return (string) $override;
    }

    return __DIR__ . '/api/hash.txt';
}

function goldappApiTokenDigest(string $token): string
{
    return 'sha256:' . hash('sha256', $token);
}

function goldappApiTokenRecord(): string
{
    $path = goldappApiTokenFile();
    if (!is_file($path)) {
        return '';
    }

    $record = trim((string) @file_get_contents($path));
    return $record;
}

function goldappHasDedicatedApiToken(): bool
{
    return goldappApiTokenRecord() !== '';
}

function goldappStoreApiToken(string $token): bool
{
    if ($token === '') {
        return false;
    }

    $path = goldappApiTokenFile();
    $directory = dirname($path);

    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        error_log('GoldApp API token: unable to create token directory.');
        return false;
    }

    $record = goldappApiTokenDigest($token) . PHP_EOL;
    $temp = @tempnam($directory, '.goldapp-token-');
    if ($temp === false) {
        error_log('GoldApp API token: unable to create temporary token file.');
        return false;
    }

    $ok = false;
    try {
        if (@file_put_contents($temp, $record, LOCK_EX) === false) {
            return false;
        }

        @chmod($temp, 0600);

        if (!@rename($temp, $path)) {
            return false;
        }

        @chmod($path, 0600);
        $ok = true;
        return true;
    } finally {
        if (!$ok && is_file($temp)) {
            @unlink($temp);
        }
    }
}

function goldappValidateDedicatedApiToken(string $provided): bool
{
    if ($provided === '') {
        return false;
    }

    $stored = goldappApiTokenRecord();
    if ($stored === '') {
        return false;
    }

    if (str_starts_with($stored, 'sha256:')) {
        $storedHash = substr($stored, 7);
        if (!preg_match('/^[a-f0-9]{64}$/i', $storedHash)) {
            error_log('GoldApp API token: invalid stored digest format.');
            return false;
        }

        return hash_equals(strtolower($storedHash), hash('sha256', $provided));
    }

    // Compatibility migration for pre-phase-5 plaintext token files.
    if (!hash_equals($stored, $provided)) {
        return false;
    }

    if (goldappStoreApiToken($provided)) {
        error_log('GoldApp API token: migrated legacy plaintext token to SHA-256 storage.');
    } else {
        // Authentication remains valid for this request; migration can retry on
        // the next successful request instead of breaking a working client.
        error_log('GoldApp API token: plaintext token authenticated but migration failed.');
    }

    return true;
}
