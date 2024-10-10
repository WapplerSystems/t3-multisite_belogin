<?php
declare(strict_types=1);

namespace WapplerSystems\MultisiteBelogin\Authentication;

use TYPO3\CMS\Core\Authentication\AbstractAuthenticationService;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\SysLog\Action\Login as SystemLogLoginAction;
use TYPO3\CMS\Core\SysLog\Error as SystemLogErrorClassification;
use TYPO3\CMS\Core\SysLog\Type as SystemLogType;
use WapplerSystems\MultisiteBelogin\Session\UserSessionManager;

class TokenAuthenticationService extends AbstractAuthenticationService
{


    public function getUser()
    {
        $token = $this->getParameterFromRequest('msblToken');
        if (!$token) {
            return false;
        }
        $username = $this->getParameterFromRequest('username');
        $user = $this->fetchUserRecord($username);
        if (!is_array($user)) {
            // Failed login attempt (no username found)
            $this->writelog(SystemLogType::LOGIN, SystemLogLoginAction::ATTEMPT, SystemLogErrorClassification::SECURITY_NOTICE, 2, 'Login-attempt from ###IP###, username \'%s\' not found!', [$username]);
            $this->logger->info('Login-attempt from username "{username}" not found!', [
                'username' => $username,
                'REMOTE_ADDR' => $this->authInfo['REMOTE_ADDR'],
            ]);
        } else {
            $this->logger->debug('User found', [
                $this->db_user['userid_column'] => $user[$this->db_user['userid_column']],
                $this->db_user['username_column'] => $user[$this->db_user['username_column']],
            ]);
        }
        return $user;

    }

    public function authUser(array $user): int
    {
        $token = $this->getParameterFromRequest('msblToken');

        $userSessionManager = UserSessionManager::create('BE');
        $sessionBackend = $userSessionManager->getSessionBackend();
        $userSessions = $sessionBackend->getAll();

        foreach ($userSessions as $userSession) {
            if ($userSession['ses_userid'] === $user['uid']) {
                $sessionData = unserialize($userSession['ses_data'] ?? '', ['allowed_classes' => false]) ?: [];
                $sessionToken = $sessionData['login_token'] ?? null;
                $sessionTimeout = $sessionData['login_token_timeout'] ?? null;
                if ($sessionToken && $sessionToken === $token && $sessionTimeout && $sessionTimeout > time()) {
                    return 200;
                }
            }
        }
        return 110;
    }

    protected function getParameterFromRequest(string $parameterName): ?string
    {
        if ((new Typo3Version())->getMajorVersion() >= 12) {
            /** @var ServerRequest $request */
            $request = $this->authInfo['request'] ?? $GLOBALS['TYPO3_REQUEST'];
            $parsedBody = $request->getParsedBody();
            if (isset($parsedBody[$parameterName])) {
                return trim((string)($parsedBody[$parameterName]));
            }
            return $request->getQueryParams()[$parameterName] ?? null;
        }
        if (isset($_POST[$parameterName])) {
            return trim((string)($_POST[$parameterName] ?? ''));
        }
        if (isset($_GET[$parameterName])) {
            return trim((string)($_GET[$parameterName]));
        }
        return null;
    }

}
