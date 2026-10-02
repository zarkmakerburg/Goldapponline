<?php

require_once dirname(__DIR__) . '/api_token.php';

function assert_true($condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$path = sys_get_temp_dir() . '/goldapp-api-token-test-' . bin2hex(random_bytes(8));
putenv('GOLDAPP_API_TOKEN_FILE=' . $path);

try {
    $token = str_repeat('a1', 32);

    assert_true(goldappStoreApiToken($token), 'new token digest should be stored');
    $stored = trim((string) file_get_contents($path));

    assert_true(str_starts_with($stored, 'sha256:'), 'stored token must use sha256 prefix');
    assert_true($stored === goldappApiTokenDigest($token), 'stored digest must match token');
    assert_true(!str_contains($stored, $token), 'plaintext token must not be stored');
    assert_true(goldappValidateDedicatedApiToken($token), 'correct token must validate');
    assert_true(!goldappValidateDedicatedApiToken($token . 'bad'), 'incorrect token must fail');

    // Simulate a pre-phase-5 installation.
    $legacyToken = 'legacy-token-' . bin2hex(random_bytes(12));
    file_put_contents($path, $legacyToken);
    chmod($path, 0600);

    assert_true(goldappValidateDedicatedApiToken($legacyToken), 'legacy plaintext token must validate once');
    $migrated = trim((string) file_get_contents($path));

    assert_true($migrated === goldappApiTokenDigest($legacyToken), 'legacy token must migrate to digest');
    assert_true(!str_contains($migrated, $legacyToken), 'legacy plaintext must be removed after migration');
    assert_true(goldappValidateDedicatedApiToken($legacyToken), 'migrated token must remain valid');

    $mode = fileperms($path) & 0777;
    assert_true($mode === 0600, 'token digest file must be mode 0600');

    fwrite(STDOUT, "GoldApp API token storage tests passed.\n");
} finally {
    @unlink($path);
    putenv('GOLDAPP_API_TOKEN_FILE');
}
