<?php

namespace WapplerSystems\MultisiteBelogin\EventListener;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\Event\AfterUserLoggedInEvent;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class AfterUserLoggedInEventListener
{

    public function __construct(
        private ConnectionPool $connectionPool
    ) {}

    public function __invoke(AfterUserLoggedInEvent $event): void
    {
        $request = $event->getRequest();
        if ($request === null) {
            return;
        }

        $user = $event->getUser();
        if (!($user instanceof BackendUserAuthentication)) {
            return;
        }

        $sessionIdentifier = $user->getSession()->getIdentifier();

        // Domain aus dem Request holen
        $uri = $request->getUri();
        if ($uri === null) {
            return;
        }
        $host = $uri->getHost();


        $connection = $this->connectionPool->getConnectionForTable('be_sessions');
        if ($connection === null) {
            return;
        }

        // Session aktualisieren
        $connection->update(
            'be_sessions',
            [
                'cookieDomain' => $host
            ],
            [
                'ses_id' => $this->hash($sessionIdentifier)
            ]
        );
    }

    public function hash(string $sessionId): string
    {
        // The sha1 hash ensures we have good length for the key.
        $key = sha1($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] . 'core-session-backend');
        return hash_hmac('sha256', $sessionId, $key);
    }

}
