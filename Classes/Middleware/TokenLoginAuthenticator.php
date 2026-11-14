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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\LoginType;
use TYPO3\CMS\Core\Authentication\Mfa\MfaRequiredException;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\SetCookieService;
use TYPO3\CMS\Core\Utility\HttpUtility;
use WapplerSystems\MultisiteBelogin\Authentication\TokenAuthenticationService;
use WapplerSystems\MultisiteBelogin\Service\LoginTokenStore;
use WapplerSystems\MultisiteBelogin\Session\UserSessionManager;

/**
 * Initializes the backend user authentication object (BE_USER) and the global LANG object.
 *
 * @internal
 */
class TokenLoginAuthenticator implements MiddlewareInterface
{

    public function __construct(private BackendUserAuthentication $backendUserAuthentication,
                                protected Context                 $context,
                                private readonly LoginTokenStore  $loginTokenStore)
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


        // The middleware redirects to the target URL after login. Only allow URLs on the host the token was
        // sent to, otherwise this endpoint is an open redirect.
        $targetUri = parse_url($url);
        $requestHost = $request->getAttribute('normalizedParams')?->getRequestHostOnly() ?? $request->getUri()->getHost();
        if (!is_array($targetUri)
            || !in_array(strtolower($targetUri['scheme'] ?? ''), ['http', 'https'], true)
            || strtolower($targetUri['host'] ?? '') !== strtolower($requestHost)
        ) {
            return new HtmlResponse('Invalid target url', 400);
        }

        if (!is_string($token) || $token === '' || $userid <= 0) {
            return new HtmlResponse('no token found', 500);
        }

        $tokenRecord = $this->loginTokenStore->consume($token, $userid);
        if ($tokenRecord === 'expired') {
            return new HtmlResponse('Token expired', 500);
        }
        if (!is_array($tokenRecord)) {
            return new HtmlResponse('no token found', 500);
        }

        //$response = new HtmlResponse('Token valid, logging in...', 200);
        $parsed = $targetUri;
        parse_str($parsed['query'] ?? '', $existing);
        $params = ['refresh' => time()];
        $parsed['query'] = http_build_query(array_merge($existing, $params));
        $url = HttpUtility::buildUrl($parsed);

        $response = new RedirectResponse($url);

        // TokenAuthenticationService only accepts this server-side attribute, so a token can't be
        // used anywhere else than on this endpoint.
        $request = $request
            ->withQueryParams([
                'msblToken' => $token,
                'userid' => $userid,
                'login_status' => LoginType::LOGIN,
            ])
            ->withAttribute(TokenAuthenticationService::VERIFIED_USER_ATTRIBUTE, $userid);
        $request = $this->withoutForeignSession($request, $tokenRecord);

        try {
            $this->backendUserAuthentication->start($request);
        } catch (MfaRequiredException) {
            // The token is only issued within a session that has already passed MFA,
            // so the new session on this domain inherits that state. In switch user mode
            // the core skips MFA for the user switched to.
            if ($tokenRecord['mfa']) {
                $this->backendUserAuthentication->setAndSaveSessionData('mfa', true);
            } elseif ($tokenRecord['switch_user_id'] === 0) {
                return new HtmlResponse('Multi-factor authentication has not been completed', 403);
            }
        }

        if ((int)($this->backendUserAuthentication->user['uid'] ?? 0) !== $tokenRecord['user_id']) {
            return new HtmlResponse('Login failed', 403);
        }

        if ($tokenRecord['switch_user_id'] > 0) {
            // Keep the switch user mode on this domain as well: the core records the original user in the history,
            // offers to exit the mode and withholds the system maintainer privileges.
            $this->backendUserAuthentication->setAndSaveSessionData('backuserid', $tokenRecord['switch_user_id']);
        }

        if ($tokenRecord['login_group'] !== '') {
            $this->backendUserAuthentication->setAndSaveSessionData(LoginTokenStore::LOGIN_GROUP_SESSION_KEY, $tokenRecord['login_group']);
        }

        if ($workspaceId > 0) {
            $this->backendUserAuthentication->setWorkspace($workspaceId);
        }

        return $this->enrichResponseWithHeadersAndCookieInformation($request, $response, $this->backendUserAuthentication);
    }

    /**
     * The core ignores login data while the request carries a session with a logged-in user and keeps that session.
     * The token state (MFA, login group, switch user mode) would then end up in the session of whoever is logged in on
     * this domain, e.g. mark a session that is still waiting for its second factor as MFA verified. A session of the
     * same user in the same switch user mode is kept, so open backend tabs on this domain survive; any other session
     * is ended and the login starts from scratch.
     */
    protected function withoutForeignSession(ServerRequestInterface $request, array $tokenRecord): ServerRequestInterface
    {
        $cookieName = $this->backendUserAuthentication->name;
        if (!isset($request->getCookieParams()[$cookieName])) {
            return $request;
        }
        $userSessionManager = UserSessionManager::create('BE');
        $session = $userSessionManager->createFromRequestOrAnonymous($request, $cookieName);
        if ($session->isNew() || $session->isAnonymous()) {
            return $request;
        }
        if ($session->getUserId() === $tokenRecord['user_id']
            && (int)$session->get('backuserid') === $tokenRecord['switch_user_id']
        ) {
            return $request;
        }
        $userSessionManager->removeSession($session);
        $cookies = $request->getCookieParams();
        unset($cookies[$cookieName]);
        return $request->withCookieParams($cookies);
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
        $cookieObject = $setCookieService->setSessionCookie($this->backendUserAuthentication->getSession(), $normalizedParams);
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
