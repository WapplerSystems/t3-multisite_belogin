
# Multisite Backend Login

TYPO3 extension for automatic cross-domain backend login. Allows editors to use backend tools (e.g. frontend preview) across all configured site domains without separate login per domain.

## Requirements

- TYPO3 v14+
- PHP 8.2+
- Backend must be accessed via HTTPS

## Installation

```bash
composer require wapplersystems/multisite-belogin
```

Add the `refresh` GET parameter to the cacheHash exclusion list in `config/system/settings.php`:

```php
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'refresh';
```

## How it works

When an editor clicks "View page" in the TYPO3 backend, the target page may be on a different domain than the backend. Without this extension, the editor would not be authenticated on that domain and could not see unpublished content or use the admin panel.

This extension solves this by:

1. **Token generation** -- When the editor triggers a frontend preview, a short-lived token (60s TTL) is generated and stored in the backend session.
2. **Redirect** -- The preview URL is rewritten to pass through the token authentication endpoint (`/typo3/msbl/tokenauth`) with the token, user ID, and original target URL.
3. **Token validation** -- The middleware intercepts the request, validates the token against the stored session data, and authenticates the user.
4. **Cookie propagation** -- A backend session cookie with `SameSite=None` is set on the response, enabling cross-domain authentication.
5. **Redirect to target** -- The editor is redirected to the original preview URL, now fully authenticated.

## Architecture

### Authentication flow

```
Backend (domain-a.com)                    Frontend (domain-b.com)
        |                                         |
        |  1. Editor clicks "View page"           |
        |  2. Token generated + stored in session  |
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
| `TokenAuthenticationService` | TYPO3 auth service (`subtype: getUserBE,authUserBE`) that validates tokens against stored sessions |
| `TokenLoginAuthenticator` | PSR-15 middleware on `/typo3/msbl/tokenauth` -- validates token, sets session cookie, redirects |
| `LoginController` | Backend route `/msbl/redirectToFrontend` -- generates token, stores in session, builds redirect URL |
| `TokenController` | Backend route for programmatic token generation (JSON API) |
| `AfterPagePreviewUriGeneratedEventListener` | Rewrites preview URIs to route through the token auth endpoint |
| `BeforeUserLogoutEventListener` | On logout: removes all other sessions for the user |
| `AfterUserLoggedOutEventListener` | On logout: cleanup of all remaining sessions |
| `UserSessionManager` | Extends core `UserSessionManager` to expose the session backend for direct session access |

### Configuration files

| File | Purpose |
|------|---------|
| `Configuration/Backend/Routes.php` | Registers `/msbl/redirectToFrontend` and `/msbl/tokenauth` backend routes |
| `Configuration/RequestMiddlewares.php` | Registers `TokenLoginAuthenticator` before backend routing |
| `Configuration/Services.yaml` | DI config + event listener registration |
| `Configuration/Icons.php` | Toolbar and module icons |

## Security considerations

- Tokens are cryptographically random (40-char hex)
- Tokens expire after 60 seconds
- Token validation uses safe `unserialize()` with `allowed_classes: false`
- Session cookies are set with `SameSite=None` (requires HTTPS)
- Failed login attempts are logged via PSR-3 logger

## License

GPL-2.0-or-later
