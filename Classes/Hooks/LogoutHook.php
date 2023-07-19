<?php

namespace WapplerSystems\MultisiteBelogin\Hooks;

use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;
use WapplerSystems\MultisiteBelogin\Session\UserSessionManager;

class LogoutHook
{

    public function execute($params, AbstractUserAuthentication $pObj)
    {
        $userSessionManager = UserSessionManager::create('BE');
        $sessionBackend = $userSessionManager->getSessionBackend();
        $userSessions = $sessionBackend->getAll();

        foreach ($userSessions as $userSession) {
            if ($userSession['ses_userid'] !== $pObj->getSession()->getIdentifier() && $userSession['ses_userid'] === $pObj->user['uid']) {
                $sessionBackend->remove($userSession['ses_id']);
            }
        }

    }

}
