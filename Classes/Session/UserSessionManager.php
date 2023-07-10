<?php

namespace WapplerSystems\MultisiteBelogin\Session;


use TYPO3\CMS\Core\Authentication\IpLocker;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class UserSessionManager extends \TYPO3\CMS\Core\Session\UserSessionManager
{

    public function getSessionBackend() : \TYPO3\CMS\Core\Session\Backend\SessionBackendInterface
    {
        return $this->sessionBackend;
    }


    /**
     * Creates a `UserSessionManager` instance for the given login type. Has
     * several optional arguments used for testing purposes to inject dummy
     * objects if needed.
     *
     * Ideally, this factory encapsulates all `TYPO3_CONF_VARS` options, so
     * the actual object does not need to consider any global state.
     *
     * @param string $loginType
     * @param int|null $sessionLifetime
     * @param SessionManager|null $sessionManager
     * @param IpLocker|null $ipLocker
     * @return static
     */
    public static function create(string $loginType, int $sessionLifetime = null, SessionManager $sessionManager = null, IpLocker $ipLocker = null): self
    {
        $sessionManager = $sessionManager ?? GeneralUtility::makeInstance(SessionManager::class);
        $ipLocker = $ipLocker ?? GeneralUtility::makeInstance(
            IpLocker::class,
            (int)($GLOBALS['TYPO3_CONF_VARS'][$loginType]['lockIP'] ?? 0),
            (int)($GLOBALS['TYPO3_CONF_VARS'][$loginType]['lockIPv6'] ?? 0)
        );
        $lifetime = (int)($GLOBALS['TYPO3_CONF_VARS'][$loginType]['lifetime'] ?? 0);
        $sessionLifetime = $sessionLifetime ?? (int)$GLOBALS['TYPO3_CONF_VARS'][$loginType]['sessionTimeout'];
        if ($sessionLifetime > 0 && $sessionLifetime < $lifetime && $lifetime > 0) {
            // If server session timeout is non-zero but less than client session timeout: Copy this value instead.
            $sessionLifetime = $lifetime;
        }
        $object = GeneralUtility::makeInstance(
            self::class,
            $sessionManager->getSessionBackend($loginType),
            $sessionLifetime,
            $ipLocker
        );
        if ($loginType === 'FE') {
            $object->setGarbageCollectionTimeoutForAnonymousSessions((int)($GLOBALS['TYPO3_CONF_VARS']['FE']['sessionDataLifetime'] ?? 0));
        }
        return $object;
    }

}
