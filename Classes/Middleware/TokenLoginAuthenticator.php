<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace WapplerSystems\MultisiteBelogin\Middleware;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\Event\AfterUserLoggedInEvent;
use TYPO3\CMS\Core\Authentication\LoginType;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\SetCookieService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Initializes the backend user authentication object (BE_USER) and the global LANG object.
 *
 * @internal
 */
class TokenLoginAuthenticator implements MiddlewareInterface
{

    public function __construct(private BackendUserAuthentication $backendUserAuthentication,
                                protected Context                 $context)
    {

    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestedUri = $request->getUri();
        if (str_starts_with($requestedUri->getPath(), '/typo3/msbl/tokenauth')) {
            return $this->processRequest($request);
        }
        return $handler->handle($request);
    }

    protected function processRequest(ServerRequestInterface $request): ResponseInterface
    {

        $token = $request->getQueryParams()['msblToken'] ?? '';
        $userid = (int)($request->getQueryParams()['userid'] ?? null);
        $url = $request->getQueryParams()['url'] ?? '';
        $workspaceId = (int)($request->getQueryParams()['workspace'] ?? 0);

        if ($userid === null) {
            return new HtmlResponse('No user id given', 500);
        }

        if (@is_file(Environment::getLegacyConfigPath() . '/LOCK_BACKEND')) {
            return new HtmlResponse('Backend is locked', 500);
        }


        $sessionConnection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('be_sessions');
        $sessions = $sessionConnection->select(
            ['ses_data', 'ses_id'],
            'be_sessions',
            ['ses_userid' => $userid]
        )->fetchAllAssociative();
        foreach ($sessions as $session) {
            if (isset($session['ses_data'])) {
                $sessionData = unserialize($session['ses_data']);
                $loginToken = $sessionData['login_token'] ?? null;

                if ($loginToken === $token) {

                    // check token timeout
                    $tokenTimeout = $sessionData['login_token_timeout'] ?? 0;
                    if ($tokenTimeout < time()) {
                        return new HtmlResponse('Token expired', 500);
                    }

                    //$response = new HtmlResponse('Token valid, logging in...', 200);
                    $url .= str_contains($url, '?') ? '&' : '?';
                    $url .= 'refresh=' . time();
                    $response = new RedirectResponse($url);

                    $request = $request->withQueryParams([
                        'msblToken' => $token,
                        'userid' => $userid,
                        'login_status' => LoginType::LOGIN,
                    ]);

                    $this->backendUserAuthentication->start($request);

                    if ($workspaceId > 0) {
                        $this->backendUserAuthentication->setWorkspace($workspaceId);
                    }

                    return $this->enrichResponseWithHeadersAndCookieInformation($request, $response, $this->backendUserAuthentication);
                }
            }
        }

        return new HtmlResponse('no token found', 500);

    }


    /**
     * Backend requests should always apply Set-Cookie information and never be cacheable.
     * This is also needed if there is a redirect from somewhere in the code.
     *
     * @throws \TYPO3\CMS\Core\Context\Exception\AspectNotFoundException
     */
    protected function enrichResponseWithHeadersAndCookieInformation(
        ServerRequestInterface    $request,
        ResponseInterface         $response,
        BackendUserAuthentication $userAuthentication
    ): ResponseInterface
    {
        $response = $this->appendCookieToResponse($response, $request->getAttribute('normalizedParams'));
        // Additional headers to never cache any PHP request should be sent at any time when
        // accessing the TYPO3 Backend
        $response = $this->applyHeadersToResponse($response);
        return $response;
    }


    public function appendCookieToResponse(ResponseInterface $response, ?NormalizedParams $normalizedParams = null): ResponseInterface
    {
        if ($normalizedParams === null) {
            $normalizedParams = NormalizedParams::createFromRequest($GLOBALS['TYPO3_REQUEST']);
        }
        $setCookieService = SetCookieService::create($this->backendUserAuthentication->name, $this->backendUserAuthentication->loginType);
        $cookieObject = $setCookieService->setSessionCookie($this->backendUserAuthentication->userSession, $normalizedParams);
        if ($cookieObject) {
            $cookieObject = $cookieObject->withSameSite(Cookie::SAMESITE_NONE);
            $response = $response->withAddedHeader('Set-Cookie', $cookieObject->__toString());
        }
        return $response;
    }

    /**
     * Adding headers to the response to avoid caching on the client side.
     * These headers will override any previous headers of these names sent.
     * Get the http headers to be sent if an authenticated user is available,
     * in order to disallow browsers to store the response on the client side.
     *
     * @return ResponseInterface the modified response object.
     */
    protected function applyHeadersToResponse(ResponseInterface $response): ResponseInterface
    {
        $headers = [
            'Expires' => 0,
            'Last-Modified' => gmdate('D, d M Y H:i:s') . ' GMT',
            'Cache-Control' => 'no-cache, no-store',
            // HTTP 1.0 compatibility, see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Pragma
            'Pragma' => 'no-cache',
        ];
        foreach ($headers as $headerName => $headerValue) {
            $response = $response->withHeader($headerName, (string)$headerValue);
        }
        return $response;
    }

}
