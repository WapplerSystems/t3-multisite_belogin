<?php
declare(strict_types=1);

namespace WapplerSystems\MultisiteBelogin\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Localization\LanguageService;
use WapplerSystems\MultisiteBelogin\Service\TokenGenerator;

class LoginController
{


    public function __construct(
        private readonly TokenGenerator $tokenGenerator,
        private readonly UriBuilder     $uriBuilder)
    {
    }


    public function redirectToFrontendAction(ServerRequestInterface $request): ResponseInterface
    {

        // TODO: check if backend cookie is already propagated to frontend domain, if yes, redirect directly

        $token = $this->tokenGenerator->generate();
        $backendUser = $this->getBackendUser();
        $backendUser->setAndSaveSessionData('login_token', $token);
        $backendUser->setAndSaveSessionData('login_token_timeout', time() + 20);

        $frontendUrl = $request->getQueryParams()['url'] ?? '';

        $uri = Uri::fromAnyScheme($frontendUrl);

        if ($uri->getHost() === $request->getUri()->getHost()) {
            return new RedirectResponse($frontendUrl);
        }

        $workspaceId = (int)$backendUser->workspace;

        $tokenAuthUri = $this->generateBackendUrl('multisitebelogin_tokenauth');
        $tokenAuthUri = $uri->getScheme() . '://' . $uri->getHost() . $tokenAuthUri . '?msblToken='.$token.'&userid='.$backendUser->user['uid'].'&workspace='.$workspaceId.'&url=' . urlencode($frontendUrl);

        return new RedirectResponse($tokenAuthUri);
    }



    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    protected function generateBackendUrl(string $route): string
    {
        return (string)$this->uriBuilder->buildUriFromRoute($route);
    }

}
