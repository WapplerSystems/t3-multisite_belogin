<?php
declare(strict_types=1);

namespace WapplerSystems\MultisiteBelogin\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use WapplerSystems\MultisiteBelogin\Service\TokenGenerator;

class TokenController
{

    protected TokenGenerator $tokenGenerator;

    public function __construct(TokenGenerator $tokenGenerator)
    {
        $this->tokenGenerator = $tokenGenerator;
    }

    public function generateAction(ServerRequestInterface $request): ResponseInterface
    {
        $token = $this->tokenGenerator->generate();
        $backendUser = $this->getBackendUser();
        $backendUser->setAndSaveSessionData('login_token', $token);
        $backendUser->setAndSaveSessionData('login_token_timeout', time() + 60);

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
