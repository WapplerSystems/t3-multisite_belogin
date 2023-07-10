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

use TYPO3\CMS\Backend\Toolbar\ToolbarItemInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;


class StatusToolbarItem implements ToolbarItemInterface
{

    public function __construct(
    ) {

        $this->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/MultisiteBelogin/sites');

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
        return $this->getFluidTemplateObject('StatusToolbarItem.html')->render();
    }

    /**
     * Render drop down
     */
    public function getDropDown(): string
    {
        if (!$this->checkAccess()) {
            return '';
        }
        $view = $this->getFluidTemplateObject('StatusToolbarItemDropDown.html');

        $corsWarning = false;
        if (!empty($GLOBALS['TYPO3_CONF_VARS']['BE']['cookieDomain'])) {
            $corsWarning = true;
        }

        $view->assignMultiple([
            'corsWarning' => $corsWarning,
        ]);
        return $view->render();
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

    /**
     * Returns current PageRenderer
     *
     * @return PageRenderer
     */
    protected function getPageRenderer()
    {
        return GeneralUtility::makeInstance(PageRenderer::class);
    }

    /**
     * Returns a new standalone view, shorthand function
     *
     * @param string $filename Which templateFile should be used.
     * @return StandaloneView
     */
    protected function getFluidTemplateObject(string $filename): StandaloneView
    {
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setLayoutRootPaths([
            'EXT:backend/Resources/Private/Layouts',
            'EXT:multisite_belogin/Resources/Private/Layouts'
        ]);
        $view->setPartialRootPaths([
            'EXT:backend/Resources/Private/Partials/ToolbarItems',
            'EXT:multisite_belogin/Resources/Private/Partials/ToolbarItems',
        ]);
        $view->setTemplateRootPaths(['EXT:multisite_belogin/Resources/Private/Templates/ToolbarItems']);

        $view->setTemplate($filename);

        $view->getRequest()->setControllerExtensionName('Backend');
        return $view;
    }
}
