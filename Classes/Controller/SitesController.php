<?php
declare(strict_types=1);

namespace WapplerSystems\MultisiteBelogin\Controller;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use TYPO3\CMS\Backend\Attribute\Controller;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Beuser\Domain\Model\BackendUser;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[Controller]
class SitesController
{


    public function listAction(ServerRequestInterface $request): ResponseInterface
    {
        $context = GeneralUtility::makeInstance(Context::class);
        $uid = $context->getPropertyFromAspect('backend.user', 'id');
        $isAdmin = $context->getPropertyFromAspect('backend.user', 'isAdmin');

        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $sites = $siteFinder->getAllSites();
        $returnArray = [];


        $permissionClause = $this->getBackendUserAuthentication()->getPagePermsClause(Permission::PAGE_SHOW);


        foreach ($sites as $site) {

            $pageRow = BackendUtility::readPageAccess($site->getRootPageId(), $permissionClause);
            if ($isAdmin || $this->getBackendUserAuthentication()->doesUserHaveAccess($pageRow, Permission::PAGE_SHOW)) {

                $aSiteLanguages = [];
                $siteLanguages = $site->getAllLanguages();
                foreach ($siteLanguages as $siteLanguage) {
                    $aSiteLanguages[] = [
                        'base' => $siteLanguage->getBase()->__toString(),
                        'host' => parse_url($siteLanguage->getBase()->__toString(), PHP_URL_HOST),
                        'protocol' => parse_url($siteLanguage->getBase()->__toString(), PHP_URL_SCHEME),
                        'title' => $siteLanguage->getTitle(),
                    ];
                }

                try {
                    $title = $site->getAttribute('websiteTitle');
                } catch (\InvalidArgumentException $e) {
                    $title = '';
                }
                if (empty($title)) {
                    $title = $site->getIdentifier();
                }
                $returnArray[$site->getRootPageId()] = [
                    'identifier' => $site->getIdentifier(),
                    'websiteTitle' => $title,
                    'siteLanguages' => $aSiteLanguages,
                ];
            }

        }

        return new JsonResponse($returnArray);
    }


    protected function getBackendUserAuthentication(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

}
