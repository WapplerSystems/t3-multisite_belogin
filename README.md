
# Multisite Backend Login

TYPO3 extension for automatic cross-domain backend login. Allows editors to use backend tools (e.g. frontend preview) across all configured site domains without separate login per domain.

## Requirements

- TYPO3 v14+
- PHP 8.2+
- Backend must be accessed via HTTPS

## Installation

```bash
composer require wapplersystems/multisite-belogin
vendor/bin/typo3 extension:setup -e multisite_belogin
```

The extension keeps its login tokens in the table `tx_multisitebelogin_token`, so run the database schema update (`extension:setup` or *Admin Tools > Maintenance > Analyze Database Structure*) after installing or updating it.

Every session backend of the TYPO3 core is supported, the database backend as well as Redis.

Add the `refresh` GET parameter to the cacheHash exclusion list in `config/system/settings.php`:

```php
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'refresh';
```

## How it works

When an editor clicks "View page" in the TYPO3 backend, the target page may be on a different domain than the backend. Without this extension, the editor would not be authenticated on that domain and could not see unpublished content or use the admin panel.

This extension solves this by:

1. **Token generation** -- When the editor triggers a frontend preview, a short-lived token (20s TTL) is generated. Only its SHA-256 hash is stored, in `tx_multisitebelogin_token`, together with the user, the MFA state and the login group of the issuing session. Tokens are only issued for URLs on a domain of a configured site.
2. **Redirect** -- The preview URL is rewritten to pass through the token authentication endpoint (`/typo3/msbl/tokenauth`) with the token, user ID, and original target URL.
3. **Token validation** -- The middleware intercepts the request, validates the token against the token table, consumes it (single use) and authenticates the user. The new session joins the login group of the issuing session. If the user has MFA enabled, the session on the target domain is only accepted as MFA-verified when the issuing session has passed MFA.
4. **Cookie propagation** -- A backend session cookie with `SameSite=None` is set on the response, enabling cross-domain authentication.
5. **Redirect to target** -- The editor is redirected to the original preview URL, now fully authenticated.

## Architecture

### Authentication flow

```
Backend (domain-a.com)                    Frontend (domain-b.com)
        |                                         |
        |  1. Editor clicks "View page"           |
        |  2. Token generated, hash stored         |
        |  3. Redirect to /typo3/msbl/tokenauth   |
        |         with ?msblToken=...&userid=...   |
        |  ─────────────────────────────────────>  |
        |                                         |  4. Middleware validates token
        |                                         |  5. BE session cookie set
        |                                         |  6. Redirect to target page
        |                                         |
```

### Components

| Component | Description |
|-----------|-------------|
| `TokenGenerator` | Generates 40-char random hex tokens via `TYPO3\CMS\Core\Crypto\Random` |
| `TokenAuthenticationService` | TYPO3 auth service (`subtype: getUserBE,authUserBE`) that authenticates the user whose token the middleware has verified; inactive on all other requests |
| `TokenLoginAuthenticator` | PSR-15 middleware on `/typo3/msbl/tokenauth` -- validates and consumes the token, carries over the MFA state, sets session cookie, redirects |
| `LoginController` | Backend route `/msbl/redirectToFrontend` -- checks the target URL against the configured sites, generates token, builds redirect URL |
| `LoginTokenStore` | Issues and consumes tokens in `tx_multisitebelogin_token`, independent of the session backend |
| `TokenController` | Token generation as JSON (currently not registered as a route) |
| `AfterPagePreviewUriGeneratedEventListener` | Rewrites preview URIs to route through the token auth endpoint |
| `BeforeUserLogoutEventListener` | On logout: removes the sessions of the same login group and their outstanding tokens; independent logins of the same user (other browser, other device) stay untouched |
| `UserSessionManager` | Extends core `UserSessionManager` to expose the session backend for direct session access |
| `StoredSessionRemover` | Removes a session by the hashed identifier the session backend lists, also for Redis |

### Configuration files

| File | Purpose |
|------|---------|
| `Configuration/Backend/Routes.php` | Registers `/msbl/redirectToFrontend` and `/msbl/tokenauth` backend routes |
| `Configuration/RequestMiddlewares.php` | Registers `TokenLoginAuthenticator` before backend routing |
| `Configuration/Services.yaml` | DI config + event listener registration |
| `Configuration/Icons.php` | Toolbar and module icons |

## Security considerations

- Tokens are cryptographically random (40-char hex)
- Tokens expire after 20 seconds and can only be used once, also with concurrent requests
- Only a hash of each token is stored
- Each session keeps all of its unexpired tokens, so issuing a new token (e.g. a second workspace preview) does not invalidate one that is still in flight
- Tokens are only accepted on `/typo3/msbl/tokenauth`, not on other backend URLs
- Tokens are only issued for domains of configured sites, and the token endpoint only redirects to its own domain
- With MFA, the login on another domain requires that the issuing session has passed MFA
- In switch user mode, the session on the other domain is in switch user mode as well (original user in the record history, "exit switch user", no system maintainer privileges)
- A logout ends all sessions that were created from one another via token login, on every domain, and discards their outstanding tokens
- Session data is read with safe `unserialize()` with `allowed_classes: false`
- Session cookies are set with `SameSite=None` (requires HTTPS)
- Failed login attempts are logged via PSR-3 logger

## License

GPL-2.0-or-later
