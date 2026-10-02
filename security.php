<?php

require_once __DIR__ . '/config.php';

/**
 * New GoldApp installs verify TLS certificates for panel connections by default.
 *
 * Existing installations created before this setting existed keep the historical
 * behavior until the operator explicitly adds $allow_insecure_panel_tls = false
 * to config.php. This avoids breaking legacy self-signed panel deployments during
 * an update while making fresh installations secure by default.
 */
function goldappAllowInsecurePanelTls(): bool
{
    global $allow_insecure_panel_tls;

    $env = getenv('GOLDAPP_ALLOW_INSECURE_PANEL_TLS');
    if ($env !== false && $env !== '') {
        return filter_var($env, FILTER_VALIDATE_BOOLEAN);
    }

    if (isset($allow_insecure_panel_tls)) {
        return (bool) $allow_insecure_panel_tls;
    }

    // Legacy config.php files do not contain the setting.
    return true;
}

function goldappPanelTlsVerifyPeer(): bool
{
    return !goldappAllowInsecurePanelTls();
}

function goldappPanelTlsVerifyHost(): int
{
    return goldappAllowInsecurePanelTls() ? 0 : 2;
}
