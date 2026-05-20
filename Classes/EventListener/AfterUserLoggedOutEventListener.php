<?php


namespace WapplerSystems\MultisiteBelogin\EventListener;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\Event\AfterUserLoggedOutEvent;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class AfterUserLoggedOutEventListener
{

    public function __construct(
        private ConnectionPool $connectionPool
    ) {}

    public function __invoke(AfterUserLoggedOutEvent $event): void
    {

        $backendUserAuthentication = $event->getUser();
        if (!($backendUserAuthentication instanceof BackendUserAuthentication)) {
            return;
        }

        $connection = $this->connectionPool->getConnectionForTable('be_sessions');


        $userId = $backendUserAuthentication->getUserId();
            $connection->delete(
                'be_sessions',
                [
                    'ses_userid' => $userId
                ]
            );

    }

}
