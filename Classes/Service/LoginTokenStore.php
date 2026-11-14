<?php
declare(strict_types=1);

namespace WapplerSystems\MultisiteBelogin\Service;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Keeps the outstanding login tokens in a table of their own, independent of the session backend (database, Redis,
 * ...). Only a hash of the token is stored. Every issued token stays valid until it expires or is consumed, so a
 * newer token never invalidates one that is still in flight (e.g. two workspace previews opened right after each
 * other).
 */
class LoginTokenStore
{
    public const TABLE = 'tx_multisitebelogin_token';

    /**
     * Identifies the sessions that belong to one login: the session that issues tokens and every session created on
     * another domain with one of them share this value, so a logout ends exactly these sessions.
     */
    public const LOGIN_GROUP_SESSION_KEY = 'login_group';

    public function __construct(
        private readonly TokenGenerator $tokenGenerator,
        private readonly ConnectionPool $connectionPool,
    ) {
    }

    public function issue(BackendUserAuthentication $backendUser, int $lifetime): string
    {
        $token = $this->tokenGenerator->generate();
        $loginGroup = $this->getOrCreateLoginGroup($backendUser);

        $connection = $this->getConnection();
        // Drop expired tokens
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->delete(self::TABLE)
            ->where($queryBuilder->expr()->lt('expires', $queryBuilder->createNamedParameter(time(), Connection::PARAM_INT)))
            ->executeStatement();

        $connection->insert(self::TABLE, [
            'token_hash' => self::hashToken($token),
            'user_id' => (int)$backendUser->user['uid'],
            'login_group' => $loginGroup,
            // The new session inherits the MFA state of the issuing session
            'mfa' => $backendUser->getSessionData('mfa') ? 1 : 0,
            'expires' => time() + $lifetime,
        ]);

        return $token;
    }

    /**
     * Consumes $token: of two concurrent requests with the same token only one gets the record.
     *
     * @return array{user_id: int, login_group: string, mfa: bool}|string the token record, or 'expired' / 'invalid'
     */
    public function consume(string $token, int $userId): array|string
    {
        $tokenHash = self::hashToken($token);
        $connection = $this->getConnection();
        $record = $connection->select(['*'], self::TABLE, ['token_hash' => $tokenHash])->fetchAssociative();
        if (!is_array($record) || (int)$record['user_id'] !== $userId) {
            return 'invalid';
        }
        if ($connection->delete(self::TABLE, ['token_hash' => $tokenHash]) !== 1) {
            return 'invalid';
        }
        if ((int)$record['expires'] < time()) {
            return 'expired';
        }
        return [
            'user_id' => (int)$record['user_id'],
            'login_group' => (string)$record['login_group'],
            'mfa' => (bool)$record['mfa'],
        ];
    }

    public function removeByLoginGroup(string $loginGroup): void
    {
        $this->getConnection()->delete(self::TABLE, ['login_group' => $loginGroup]);
    }

    public static function getLoginGroup(array $sessionData): ?string
    {
        $loginGroup = $sessionData[self::LOGIN_GROUP_SESSION_KEY] ?? null;
        return is_string($loginGroup) && $loginGroup !== '' ? $loginGroup : null;
    }

    protected static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Takes the login group from the stored session data rather than the copy loaded at the beginning of this
     * request, so two concurrent requests don't create two groups.
     */
    protected function getOrCreateLoginGroup(BackendUserAuthentication $backendUser): string
    {
        $loginGroup = self::getLoginGroup($this->getStoredSessionData($backendUser) ?? [])
            ?? self::getLoginGroup([self::LOGIN_GROUP_SESSION_KEY => $backendUser->getSessionData(self::LOGIN_GROUP_SESSION_KEY)]);
        if ($loginGroup === null) {
            $loginGroup = $this->tokenGenerator->generate();
        }
        if ($backendUser->getSessionData(self::LOGIN_GROUP_SESSION_KEY) !== $loginGroup) {
            // Drop the token slots of older versions
            $backendUser->setSessionData('login_token', null);
            $backendUser->setSessionData('login_token_timeout', null);
            $backendUser->setSessionData('login_tokens', null);
            $backendUser->setAndSaveSessionData(self::LOGIN_GROUP_SESSION_KEY, $loginGroup);
        }
        return $loginGroup;
    }

    /**
     * @return array|null the session data as currently stored, null if it can't be read
     */
    protected function getStoredSessionData(BackendUserAuthentication $backendUser): ?array
    {
        try {
            $record = GeneralUtility::makeInstance(SessionManager::class)
                ->getSessionBackend('BE')
                ->get($backendUser->getSession()->getIdentifier());
        } catch (\Throwable) {
            return null;
        }
        $sessionData = unserialize((string)($record['ses_data'] ?? ''), ['allowed_classes' => false]);
        return is_array($sessionData) ? $sessionData : [];
    }

    protected function getConnection(): Connection
    {
        return $this->connectionPool->getConnectionForTable(self::TABLE);
    }
}
