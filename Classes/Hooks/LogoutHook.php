<?php

namespace WapplerSystems\MultisiteBelogin\Hooks;

use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;
use TYPO3\CMS\Core\Session\Backend\HashableSessionBackendInterface;
use WapplerSystems\MultisiteBelogin\Session\UserSessionManager;

class LogoutHook
{

    public function execute($params, AbstractUserAuthentication $pObj)
    {
        $userSessionManager = UserSessionManager::create('BE');
        $sessionBackend = $userSessionManager->getSessionBackend();
        $userSessions = $sessionBackend->getAll();

        if ($pObj->user === null) {
            return;
        }
        $sessionIdentifier = $pObj->getSession()->getIdentifier();
        if ($sessionBackend instanceof HashableSessionBackendInterface) {
            $sessionIdentifier = $sessionBackend->hash($sessionIdentifier);
        }

        foreach ($userSessions as $userSession) {
            if ($userSession['ses_id'] !== $sessionIdentifier && $userSession['ses_userid'] === $pObj->user['uid']) {
                $sessionBackend->remove($userSession['ses_id']);
            }
        }

    }

}
