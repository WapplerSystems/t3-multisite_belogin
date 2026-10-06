<?php
declare(strict_types=1);

namespace WapplerSystems\MultisiteBelogin\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Site\SiteFinder;
use WapplerSystems\MultisiteBelogin\Service\TokenGenerator;

class LoginController
{


    public function __construct(
        private readonly TokenGenerator $tokenGenerator,
        private readonly UriBuilder     $uriBuilder,
        private readonly SiteFinder     $siteFinder)
    {
    }


    public function redirectToFrontendAction(ServerRequestInterface $request): ResponseInterface
    {

        // TODO: check if backend cookie is already propagated to frontend domain, if yes, redirect directly

        $frontendUrl = $request->getQueryParams()['url'] ?? '';
        if (!is_string($frontendUrl)) {
            return new HtmlResponse('Invalid url', 400);
        }

        $uri = Uri::fromAnyScheme($frontendUrl);
        if (!in_array(strtolower($uri->getScheme()), ['http', 'https'], true) || $uri->getHost() === '') {
            return new HtmlResponse('Invalid url', 400);
        }

        if (strtolower($uri->getHost()) === strtolower($request->getUri()->getHost())) {
            return new RedirectResponse($frontendUrl);
        }

        // The login token is sent to the host of the url, so it must be one of our own sites
        if (!in_array(strtolower($uri->getHost()), $this->getSiteHosts(), true)) {
            return new HtmlResponse('Url does not belong to a site of this installation', 400);
        }

        $token = $this->tokenGenerator->generate();
        $backendUser = $this->getBackendUser();
        $backendUser->setAndSaveSessionData('login_token', $token);
        $backendUser->setAndSaveSessionData('login_token_timeout', time() + 20);

        $workspaceId = (int)$backendUser->workspace;

        $tokenAuthUri = $this->generateBackendUrl('multisitebelogin_tokenauth');
        $tokenAuthUri = $uri->getScheme() . '://' . $uri->getHost() . $tokenAuthUri . '?msblToken='.$token.'&userid='.$backendUser->user['uid'].'&workspace='.$workspaceId.'&url=' . urlencode($frontendUrl);

        return new RedirectResponse($tokenAuthUri);
    }



    /**
     * @return string[] lowercased hosts of all sites and site languages
     */
    protected function getSiteHosts(): array
    {
        $hosts = [];
        foreach ($this->siteFinder->getAllSites() as $site) {
            $hosts[] = $site->getBase()->getHost();
            foreach ($site->getAllLanguages() as $language) {
                $hosts[] = $language->getBase()->getHost();
            }
        }
        return array_values(array_unique(array_filter(array_map('strtolower', $hosts))));
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
