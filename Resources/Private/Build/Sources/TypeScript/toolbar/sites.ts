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

import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import { AjaxResponse } from '@typo3/core/ajax/ajax-response.js';
import RegularEvent from '@typo3/core/event/regular-event.js';
import Icons from '@typo3/backend/icons.js';
import PersistentStorage from '@typo3/backend/storage/persistent.js';
import Viewport from '@typo3/backend/viewport.js';

/**
 * Explicit selectors to avoid nesting queries
 */
enum MultisiteBeloginSelector {
  element = '#wapplersystems-multisitebelogin-backend-toolbaritems-statustoolbaritem',
  icon = '#wapplersystems-multisitebelogin-backend-toolbaritems-statustoolbaritem .toolbar-item-icon .t3js-icon',
  menu = '#wapplersystems-multisitebelogin-backend-toolbaritems-statustoolbaritem .dropdown-menu',
  data = '[data-systeminformation-data]',
  badge = '[data-systeminformation-badge]',
  message = '[data-systeminformation-message-module]',
  messageLink = '[data-systeminformation-message-module] a'
}

interface MultisiteBeloginData {
  count: number,
  severityBadgeClass: string,
}

interface MultisiteBeloginMessageData {
  count: number,
  status: string,
  module: string,
  params: string
}

/**
 */
class MultisiteBeloginStatusMenu {
  private timer: number = null;

  constructor () {
    new RegularEvent('click', this.handleMessageLinkClick)
      .delegateTo(document, MultisiteBeloginSelector.messageLink);
    Viewport.Topbar.Toolbar.registerEvent(this.updateMenu);
  }

  private static getData (): MultisiteBeloginData {
    const element = document.querySelector(MultisiteBeloginSelector.data) as HTMLElement;
    const data: DOMStringMap = element.dataset;
    return {
      count: data.systeminformationDataCount ? parseInt(data.systeminformationDataCount, 10) : 0,
      severityBadgeClass: data.systeminformationDataSeveritybadgeclass ?? '',
    };
  }

  private static getMessageDataFromElement (element: HTMLElement): MultisiteBeloginMessageData {
    const data: DOMStringMap = element.dataset;
    return {
      count: data.systeminformationMessageCount ? parseInt(data.systeminformationMessageCount, 10) : 0,
      status: data.systeminformationMessageStatus ?? '',
      module: data.systeminformationMessageModule ?? '',
      params: data.systeminformationMessageParams ?? '',
    };
  }

  private static updateBadge (): void {
    const data = MultisiteBeloginStatusMenu.getData();
    const element = document.querySelector(MultisiteBeloginSelector.badge) as HTMLElement;

    // ensure all default classes are available and previous
    // (at this time in processing unknown) class is removed
    element.removeAttribute('class');
    element.classList.add('toolbar-item-badge');
    element.classList.add('badge');
    if (data.severityBadgeClass !== '') {
      element.classList.add(data.severityBadgeClass);
    }

    element.textContent = data.count.toString();
    element.classList.toggle('hidden', !(data.count > 0));
  }

  private updateMenu = (): void => {
    const toolbarItemIcon = document.querySelector(MultisiteBeloginSelector.icon);
    const currentIcon = toolbarItemIcon.cloneNode(true);

    if (this.timer !== null) {
      clearTimeout(this.timer);
      this.timer = null;
    }

    Icons.getIcon('spinner-circle-light', Icons.sizes.small).then((spinner: string): void => {
      toolbarItemIcon.replaceWith(document.createRange().createContextualFragment(spinner));
    });

    (new AjaxRequest(TYPO3.settings.ajaxUrls.multisitebelogin_sites)).get().then(async (response: AjaxResponse): Promise<void> => {
      document.querySelector(MultisiteBeloginSelector.menu).innerHTML = await response.resolve();
      MultisiteBeloginStatusMenu.updateBadge();
    }).finally((): void => {
      document.querySelector(MultisiteBeloginSelector.icon).replaceWith(currentIcon);
      // reload error data every five minutes
      this.timer = setTimeout(this.updateMenu, 1000 * 300);
    });
  };

  /**
   * Updates the UC and opens the linked module
   */
  private handleMessageLinkClick (event: Event, target: HTMLElement): void {
    const messageData = MultisiteBeloginStatusMenu.getMessageDataFromElement(target.closest(MultisiteBeloginSelector.message));
    if (messageData.module === '') {
      return;
    }
    event.preventDefault();
    event.stopPropagation();

    const moduleStorageObject: { [key: string]: Object } = {};
    const timestamp = Math.floor(Date.now() / 1000);
    let storedMultisiteBeloginSettings = {};
    if (PersistentStorage.isset('systeminformation')) {
      storedMultisiteBeloginSettings = JSON.parse(PersistentStorage.get('systeminformation'));
    }

    moduleStorageObject[messageData.module] = { lastAccess: timestamp };
    Object.assign(storedMultisiteBeloginSettings, moduleStorageObject);
    const ajax = PersistentStorage.set('systeminformation', JSON.stringify(storedMultisiteBeloginSettings));
    ajax.then((): void => {
      // finally, open the module now
      TYPO3.ModuleMenu.App.showModule(messageData.module, messageData.params);
      Viewport.Topbar.refresh();
    });
  }
}

export default new MultisiteBeloginStatusMenu();
