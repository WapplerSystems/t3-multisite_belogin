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

namespace WapplerSystems\MultisiteBelogin\Backend\ToolbarItems;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Module\MenuModule;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Toolbar\RequestAwareToolbarItemInterface;
use TYPO3\CMS\Backend\Toolbar\ToolbarItemInterface;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\DebugUtility;


class StatusToolbarItem implements ToolbarItemInterface, RequestAwareToolbarItemInterface
{
    protected ?MenuModule $multisitebeloginModule = null;
    private ServerRequestInterface $request;

    public function __construct(
        ModuleProvider $moduleProvider,
        private readonly BackendViewFactory $backendViewFactory,
    ) {

        $multisitebeloginModule = $moduleProvider->getModuleForMenu('multisitebelogin', $this->getBackendUser());
        if ($multisitebeloginModule && $multisitebeloginModule->hasSubModules()) {
            $this->multisitebeloginModule = $multisitebeloginModule;
        }
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    /**
     * Users see this if a module is available
     */
    public function checkAccess(): bool
    {
        return true;
    }

    /**
     * Render help icon
     */
    public function getItem(): string
    {
        $view = $this->backendViewFactory->create($this->request,['wapplersystems/multisite-belogin']);
        return $view->render('ToolbarItems/StatusToolbarItem');
    }

    /**
     * Render drop down
     */
    public function getDropDown(): string
    {

        $view = $this->backendViewFactory->create($this->request,['wapplersystems/multisite-belogin']);
        if ($this->multisitebeloginModule !== null) {
            $view->assign('modules', $this->multisitebeloginModule->getSubModules());
        }
        return $view->render('ToolbarItems/StatusToolbarItemDropDown');
    }

    /**
     * No additional attributes needed.
     */
    public function getAdditionalAttributes(): array
    {
        return [];
    }

    /**
     * This item has a drop-down
     */
    public function hasDropDown(): bool
    {
        return true;
    }

    /**
     * Position relative to others
     */
    public function getIndex(): int
    {
        return 80;
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
