# GoldApp Online security policy

## Secure defaults

Fresh GoldApp Online installations use the following defaults:

- panel HTTPS certificates and hostnames are verified;
- the management API does not accept the Telegram bot token as an API credential;
- Telegram webhook secrets are sent through `X-Telegram-Bot-Api-Secret-Token`, not embedded in webhook URLs.

## Existing installations

GoldApp preserves compatibility with configurations created before security phase 2:

- if `$allow_insecure_panel_tls` is absent from an existing `config.php`, the historical panel TLS behavior is retained;
- if `$allow_legacy_api_bot_token` is absent, the historical management-API fallback remains available until a dedicated API token is generated.

Operators should migrate an existing installation by adding:

```php
$allow_insecure_panel_tls = false;
$allow_legacy_api_bot_token = false;
```

Before disabling the legacy API-token fallback, generate a dedicated management API token with the bot's `/token2` command and update API clients.

## Self-signed panel certificates

Do not disable TLS verification globally unless it is unavoidable. Prefer a certificate issued by a trusted CA. For a legacy trusted panel that intentionally uses a self-signed certificate, compatibility can temporarily be enabled with:

```php
$allow_insecure_panel_tls = true;
```

or by setting `GOLDAPP_ALLOW_INSECURE_PANEL_TLS=1` in the runtime environment.

## Webhook compatibility

Newly registered webhooks use Telegram's `secret_token` mechanism. GoldApp temporarily accepts the historical `?secret=...` query parameter so existing Telegram webhook registrations continue working until they are refreshed. Calling `table.php` through the normal installation/update flow refreshes the primary webhook to the header-based mechanism.

## Reporting

Do not include bot tokens, API tokens, panel credentials, database credentials, webhook secrets, cookies, or subscription credentials in public security reports.
