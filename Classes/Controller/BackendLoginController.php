<?php
declare(strict_types=1);

namespace WapplerSystems\MultisiteBelogin\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\AjaxLoginController;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\FormProtection\BackendFormProtection;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Session\UserSessionManager;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BackendLoginController extends AjaxLoginController
{


    public function loginAction(ServerRequestInterface $request): ResponseInterface
    {
        $origin = $request->getHeader('Origin');

        if (empty($origin)) {
            return new JsonResponse(['login' => ['timed_out' => true, 'no-origin' => true]]);
        }
        $originDomain = $this->getOriginDomain($origin);
        if (empty($originDomain)) {
            return new JsonResponse(['login' => ['timed_out' => true, 'no-origin-domain' => true]]);
        }


        if ($this->isAuthorizedBackendSession()) {
            $result = ['success' => true];
            if ($this->hasLoginBeenProcessed($request)) {
                /** @var BackendFormProtection $formProtection */
                $formProtection = FormProtectionFactory::get();
                $formProtection->setSessionTokenFromRegistry();
                $formProtection->persistSessionToken();
            }
        } else {
            $result = ['success' => false, 'no-authorized-backend-session' => true];
        }
        return new JsonResponse(['login' => $result], 200, ['Access-Control-Allow-Origin' => $originDomain, 'Access-Control-Allow-Credentials' => 'true']);
    }


    public function statusAction(ServerRequestInterface $request): ResponseInterface
    {
        $origin = $request->getHeader('Origin');

        if (empty($origin)) {
            return new JsonResponse(['login' => ['timed_out' => true, 'no-origin' => true]]);
        }
        $originDomain = $this->getOriginDomain($origin);
        if (empty($originDomain)) {
            return new JsonResponse(['login' => ['timed_out' => true, 'no-origin-domain' => true]]);
        }

        $session = [
            'timed_out' => false,
            'will_time_out' => false,
            'locked' => false,
            'alive' => true,
        ];
        $backendUser = $this->getBackendUser();

        if (@is_file(Environment::getLegacyConfigPath() . '/LOCK_BACKEND')) {
            $session['locked'] = true;
        } elseif (!isset($backendUser->user['uid'])) {
            $session['timed_out'] = true;
            $session['alive'] = false;
        } else {
            $sessionManager = UserSessionManager::create('BE');
            // If 120 seconds from now is later than the session timeout, we need to show the refresh dialog.
            // 120 is somewhat arbitrary to allow for a little room during the countdown and load times, etc.
            $session['will_time_out'] = $sessionManager->willExpire($backendUser->getSession(), 120);
        }
        return new JsonResponse(['login' => $session], 200, ['Access-Control-Allow-Origin' => $originDomain, 'Access-Control-Allow-Credentials' => 'true']);
    }

    private function getOriginDomain(array $origin) : ?string {

        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $sites = $siteFinder->getAllSites();
        $bases = [];
        foreach ($sites as $site) {
            $siteLanguages = $site->getAllLanguages();
            foreach ($siteLanguages as $siteLanguage) {
                $bases[] = parse_url($siteLanguage->getBase()->__toString(),PHP_URL_HOST);
            }
        }
        $originDomain = parse_url($origin[0],PHP_URL_HOST);
        if (!in_array($originDomain, $bases, true)) {
            return null;
        }
        return $origin[0];
    }


    protected function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }

}
