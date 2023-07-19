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
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\RateLimiter\LimiterInterface;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Backend\Routing\RouteRedirect;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\Mfa\MfaRequiredException;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Controller\ErrorPageController;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\RateLimiter\RateLimiterFactory;
use TYPO3\CMS\Core\RateLimiter\RequestRateLimitedException;
use TYPO3\CMS\Core\Session\UserSessionManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;

/**
 * Initializes the backend user authentication object (BE_USER) and the global LANG object.
 *
 * @internal
 */
class BackendUserAuthenticator extends \TYPO3\CMS\Backend\Middleware\BackendUserAuthenticator
{
    use LoggerAwareTrait;

    /**
     * Check if the user is required for the request.
     * If we're trying to do a login or an ajax login, don't require a user.
     *
     * @param Route $route the Route path to check against, something like '
     * @return bool true when the Route requires an authenticated backend user
     */
    protected function isLoggedInBackendUserRequired(Route $route): bool
    {
        if ($route->getPath() === '/ajax/msbl/status' || $route->getPath() === '/ajax/msbl/login' || $route->getPath() === '/ajax/msbl/preflight') {
            return false;
        }
        return in_array($route->getPath(), $this->publicRoutes, true) === false;
    }


}
