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

## Admin panel sessions

GoldApp admin sessions are intentionally short-lived:

- idle timeout: 30 minutes;
- absolute session lifetime: 12 hours;
- session ID regeneration interval: 15 minutes;
- logout clears both server-side session state and the session cookie.

The panel sends defensive browser headers including frame denial, MIME sniffing protection, no-referrer policy, restricted browser permissions, and no-store caching.

## Mini App bearer tokens

Tokens issued by the Telegram Web App verification endpoint now receive a 24-hour expiry timestamp. Mini App requests reject phase-3 tokens after expiry.

Legacy user rows that do not yet have an expiry timestamp remain temporarily compatible. The next successful Telegram Web App verification replaces the token and writes an expiry time.

## Request limits

JSON management API requests are limited to 1 MiB. Telegram Mini App verification payloads are limited to 128 KiB. These limits are intended to reduce accidental and malicious memory pressure before JSON parsing.

## Management API token storage

GoldApp management API credentials are one-way stored:

- `/token2` generates 32 random bytes (256 bits) and returns the raw token once to the administrator;
- disk storage contains only `sha256:<digest>` with file mode `0600`;
- API requests hash the presented token and compare digests with `hash_equals`;
- pre-phase-5 plaintext `api/hash.txt` files remain compatible and are automatically converted to digest storage after the first successful authentication;
- an invalid request never triggers migration;
- the Telegram bot-token compatibility fallback remains governed separately by `$allow_legacy_api_bot_token`.

Because these API tokens have high cryptographic entropy, SHA-256 digest storage prevents straightforward recovery of the original credential if the token file is disclosed.

