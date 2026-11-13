<?php

namespace WapplerSystems\MultisiteBelogin\EventListener;

use TYPO3\CMS\Core\Authentication\Event\BeforeUserLogoutEvent;
use TYPO3\CMS\Core\Session\Backend\HashableSessionBackendInterface;
use WapplerSystems\MultisiteBelogin\Service\LoginTokenStore;
use WapplerSystems\MultisiteBelogin\Session\UserSessionManager;

/**
 * Ends the sessions this extension created on other domains from the session that logs out (and the session they
 * were created from), so the user is logged out everywhere. Sessions of the same user that were opened by a separate
 * login, e.g. in another browser or on another device, stay untouched.
 */
final class BeforeUserLogoutEventListener
{
    public function __invoke(BeforeUserLogoutEvent $event): void
    {
        $user = $event->getUser();
        if ($user->user === null) {
            return;
        }

        $loginGroup = LoginTokenStore::getLoginGroup([
            LoginTokenStore::LOGIN_GROUP_SESSION_KEY => $user->getSessionData(LoginTokenStore::LOGIN_GROUP_SESSION_KEY),
        ]);
        if ($loginGroup === null) {
            // No cross-domain login has happened from this session
            return;
        }

        $userSessionManager = UserSessionManager::create('BE');
        $sessionBackend = $userSessionManager->getSessionBackend();

        $sessionIdentifier = $user->getSession()->getIdentifier();
        if ($sessionBackend instanceof HashableSessionBackendInterface) {
            $sessionIdentifier = $sessionBackend->hash($sessionIdentifier);
        }

        foreach ($sessionBackend->getAll() as $userSession) {
            if ($userSession['ses_id'] === $sessionIdentifier
                || (int)$userSession['ses_userid'] !== (int)$user->user['uid']
            ) {
                continue;
            }
            $sessionData = unserialize((string)($userSession['ses_data'] ?? ''), ['allowed_classes' => false]);
            if (is_array($sessionData) && LoginTokenStore::getLoginGroup($sessionData) === $loginGroup) {
                $sessionBackend->remove($userSession['ses_id']);
            }
        }
    }
}
