<?php
declare(strict_types=1);

namespace WapplerSystems\MultisiteBelogin\Session;

use TYPO3\CMS\Core\Session\Backend\RedisSessionBackend;
use TYPO3\CMS\Core\Session\Backend\SessionBackendInterface;

/**
 * Removes a session by the identifier SessionBackendInterface::getAll() returns. Hashable backends return the hashed
 * identifier there, while remove() expects the plain one: DatabaseSessionBackend matches both, RedisSessionBackend
 * hashes it a second time and misses the session, so its key is deleted directly.
 *
 * Extends RedisSessionBackend only to reach its connection and key name of another instance.
 */
final class StoredSessionRemover extends RedisSessionBackend
{
    public static function removeStored(SessionBackendInterface $sessionBackend, string $storedSessionId): bool
    {
        if (!$sessionBackend instanceof RedisSessionBackend) {
            return $sessionBackend->remove($storedSessionId);
        }
        $sessionBackend->initializeConnection();
        $deleteResult = $sessionBackend->redis->del($sessionBackend->getSessionKeyName($storedSessionId));
        return is_int($deleteResult) && $deleteResult >= 1;
    }
}
