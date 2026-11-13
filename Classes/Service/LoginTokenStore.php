<?php
declare(strict_types=1);

namespace WapplerSystems\MultisiteBelogin\Service;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Keeps the outstanding login tokens of a backend session as a map token => expiry timestamp in the session data.
 * Every issued token stays valid until it expires or is consumed, so a newer token never invalidates one that is
 * still in flight (e.g. two workspace previews opened right after each other).
 */
class LoginTokenStore
{
    public const SESSION_KEY = 'login_tokens';

    /**
     * Identifies the sessions that belong to one login: the session that issues tokens and every session created on
     * another domain with one of them share this value, so a logout ends exactly these sessions.
     */
    public const LOGIN_GROUP_SESSION_KEY = 'login_group';

    /**
     * Upper bound for outstanding tokens per session, so the session data can't grow without limit.
     */
    protected const MAX_TOKENS = 20;

    public function __construct(private readonly TokenGenerator $tokenGenerator)
    {
    }

    public function issue(BackendUserAuthentication $backendUser, int $lifetime): string
    {
        $token = $this->tokenGenerator->generate();

        // Start from the stored session data rather than the copy loaded at the beginning of this request, so a
        // token consumed in the meantime on another host is not written back.
        $tokens = $this->getStoredTokens($backendUser) ?? $backendUser->getSessionData(self::SESSION_KEY);
        $tokens = self::withoutExpired(is_array($tokens) ? $tokens : []);
        $tokens[$token] = time() + $lifetime;
        $tokens = array_slice($tokens, -self::MAX_TOKENS, null, true);

        if (!is_string($backendUser->getSessionData(self::LOGIN_GROUP_SESSION_KEY))) {
            $backendUser->setSessionData(self::LOGIN_GROUP_SESSION_KEY, $this->tokenGenerator->generate());
        }

        // Drop the single token slot of older versions
        $backendUser->setSessionData('login_token', null);
        $backendUser->setSessionData('login_token_timeout', null);
        $backendUser->setAndSaveSessionData(self::SESSION_KEY, $tokens);

        return $token;
    }

    /**
     * @return string|null the stored token that matches $token, compared in constant time
     */
    public static function findToken(array $sessionData, string $token): ?string
    {
        $tokens = $sessionData[self::SESSION_KEY] ?? [];
        if (!is_array($tokens)) {
            return null;
        }
        $found = null;
        foreach (array_keys($tokens) as $storedToken) {
            if (hash_equals((string)$storedToken, $token)) {
                $found = (string)$storedToken;
            }
        }
        return $found;
    }

    public static function getLoginGroup(array $sessionData): ?string
    {
        $loginGroup = $sessionData[self::LOGIN_GROUP_SESSION_KEY] ?? null;
        return is_string($loginGroup) && $loginGroup !== '' ? $loginGroup : null;
    }

    public static function isExpired(array $sessionData, string $storedToken): bool
    {
        return (int)($sessionData[self::SESSION_KEY][$storedToken] ?? 0) < time();
    }

    /**
     * Removes $storedToken and all expired tokens, leaves every other outstanding token untouched.
     */
    public static function withoutToken(array $sessionData, string $storedToken): array
    {
        $tokens = $sessionData[self::SESSION_KEY] ?? [];
        $tokens = self::withoutExpired(is_array($tokens) ? $tokens : []);
        unset($tokens[$storedToken]);
        if ($tokens === []) {
            unset($sessionData[self::SESSION_KEY]);
        } else {
            $sessionData[self::SESSION_KEY] = $tokens;
        }
        return $sessionData;
    }

    protected static function withoutExpired(array $tokens): array
    {
        $now = time();
        return array_filter($tokens, static fn($expires): bool => (int)$expires >= $now);
    }

    protected function getStoredTokens(BackendUserAuthentication $backendUser): ?array
    {
        try {
            $record = GeneralUtility::makeInstance(SessionManager::class)
                ->getSessionBackend('BE')
                ->get($backendUser->getSession()->getIdentifier());
        } catch (\Throwable) {
            return null;
        }
        $sessionData = unserialize((string)($record['ses_data'] ?? ''), ['allowed_classes' => false]);
        $tokens = is_array($sessionData) ? ($sessionData[self::SESSION_KEY] ?? []) : [];
        return is_array($tokens) ? $tokens : [];
    }
}
