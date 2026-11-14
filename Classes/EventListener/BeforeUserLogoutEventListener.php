<?php

namespace WapplerSystems\MultisiteBelogin\EventListener;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\Event\BeforeUserLogoutEvent;
use TYPO3\CMS\Core\Session\Backend\HashableSessionBackendInterface;
use WapplerSystems\MultisiteBelogin\Service\LoginTokenStore;
use WapplerSystems\MultisiteBelogin\Session\StoredSessionRemover;
use WapplerSystems\MultisiteBelogin\Session\UserSessionManager;

/**
 * Ends the sessions this extension created on other domains from the session that logs out (and the session they
 * were created from), so the user is logged out everywhere. Sessions of the same user that were opened by a separate
 * login, e.g. in another browser or on another device, stay untouched.
 *
 * Sessions are matched by their login group only, not by user id: the group id is random and only ever copied from
 * the issuing session, and a session that was switched to another user ("switch user") keeps its group, so the
 * sessions it opened on other domains for that user are ended as well.
 */
final class BeforeUserLogoutEventListener
{
    public function __construct(private readonly LoginTokenStore $loginTokenStore)
    {
    }

    public function __invoke(BeforeUserLogoutEvent $event): void
    {
        $user = $event->getUser();
        // The event is dispatched for frontend logouts as well
        if (!$user instanceof BackendUserAuthentication || $user->user === null) {
            return;
        }

        $loginGroup = LoginTokenStore::getLoginGroup([
            LoginTokenStore::LOGIN_GROUP_SESSION_KEY => $user->getSessionData(LoginTokenStore::LOGIN_GROUP_SESSION_KEY),
        ]);
        if ($loginGroup === null) {
            // No cross-domain login has happened from this session
            return;
        }

        // Tokens still in flight would otherwise open new sessions of the group after the logout
        $this->loginTokenStore->removeByLoginGroup($loginGroup);

        $userSessionManager = UserSessionManager::create('BE');
        $sessionBackend = $userSessionManager->getSessionBackend();

        $sessionIdentifier = $user->getSession()->getIdentifier();
        if ($sessionBackend instanceof HashableSessionBackendInterface) {
            $sessionIdentifier = $sessionBackend->hash($sessionIdentifier);
        }

        foreach ($sessionBackend->getAll() as $userSession) {
            if ($userSession['ses_id'] === $sessionIdentifier) {
                continue;
            }
            $sessionData = unserialize((string)($userSession['ses_data'] ?? ''), ['allowed_classes' => false]);
            if (is_array($sessionData) && LoginTokenStore::getLoginGroup($sessionData) === $loginGroup) {
                StoredSessionRemover::removeStored($sessionBackend, $userSession['ses_id']);
            }
        }
    }
}
