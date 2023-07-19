<?php

namespace WapplerSystems\MultisiteBelogin\EventListener;

use TYPO3\CMS\Core\Authentication\Event\BeforeUserLogoutEvent;
use TYPO3\CMS\Core\Session\Backend\HashableSessionBackendInterface;
use WapplerSystems\MultisiteBelogin\Session\UserSessionManager;

final class BeforeUserLogoutEventListener
{
    public function __invoke(BeforeUserLogoutEvent $event): void
    {

        $userSessionManager = UserSessionManager::create('BE');
        $sessionBackend = $userSessionManager->getSessionBackend();
        $userSessions = $sessionBackend->getAll();

        if ($event->getUser()->user === null) {
            return;
        }
        $sessionIdentifier = $event->getUser()->getSession()->getIdentifier();

        if ($sessionBackend instanceof HashableSessionBackendInterface) {
            $sessionIdentifier = $sessionBackend->hash($sessionIdentifier);
        }

        foreach ($userSessions as $userSession) {
            if ($userSession['ses_id'] !== $sessionIdentifier && $userSession['ses_userid'] === $event->getUser()->user['uid']) {
                $sessionBackend->remove($userSession['ses_id']);
            }
        }

    }
}
