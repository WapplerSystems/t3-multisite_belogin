<?php
declare(strict_types=1);

namespace WapplerSystems\MultisiteBelogin\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use WapplerSystems\MultisiteBelogin\Service\LoginTokenStore;

class TokenController
{

    protected LoginTokenStore $loginTokenStore;

    public function __construct(LoginTokenStore $loginTokenStore)
    {
        $this->loginTokenStore = $loginTokenStore;
    }

    public function generateAction(ServerRequestInterface $request): ResponseInterface
    {
        $backendUser = $this->getBackendUser();
        $token = $this->loginTokenStore->issue($backendUser, 60);

        return new JsonResponse(['token' => $token, 'username' => $backendUser->user['username']]);
    }


    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

}
