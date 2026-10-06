<?php
declare(strict_types=1);

namespace WapplerSystems\MultisiteBelogin\Authentication;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\AuthenticationService;

/**
 * Authenticates a backend user whose login token has already been verified and consumed by
 * TokenLoginAuthenticator. The middleware passes the user id as a request attribute; request
 * attributes can't be set by the client, so the service is inert on every other request.
 */
class TokenAuthenticationService extends AuthenticationService
{

    public const VERIFIED_USER_ATTRIBUTE = 'multisite_belogin.verifiedUserId';

    public function getUser()
    {
        $userId = $this->getVerifiedUserId();
        if ($userId === null) {
            return false;
        }

        $user = $this->fetchUserRecord('', 'uid=' . $userId);
        if (!is_array($user)) {
            $this->logger->info('Login-attempt with token for user id {userid}, user not found!', [
                'userid' => $userId,
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
        $userId = $this->getVerifiedUserId();
        if ($userId === null) {
            // Not a token login, let the other services decide
            return 100;
        }
        return $userId === (int)$user['uid'] ? 200 : 0;
    }

    protected function getVerifiedUserId(): ?int
    {
        $request = $this->authInfo['request'] ?? null;
        if (!$request instanceof ServerRequestInterface) {
            return null;
        }
        $userId = $request->getAttribute(self::VERIFIED_USER_ATTRIBUTE);
        return is_int($userId) && $userId > 0 ? $userId : null;
    }

}
