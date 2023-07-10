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
import RegularEvent from '@typo3/core/event/regular-event.js';
import Icons from '@typo3/backend/icons.js';
import PersistentStorage from '@typo3/backend/storage/persistent.js';
import Viewport from '@typo3/backend/viewport.js';

/**
 * Explicit selectors to avoid nesting queries
 */
var MultisiteBeloginSelector;
(function (MultisiteBeloginSelector) {
  MultisiteBeloginSelector["element"] = "#wapplersystems-multisitebelogin-backend-toolbaritems-statustoolbaritem";
  MultisiteBeloginSelector["icon"] = "#wapplersystems-multisitebelogin-backend-toolbaritems-statustoolbaritem .toolbar-item-icon .t3js-icon";
  MultisiteBeloginSelector["menu"] = "#wapplersystems-multisitebelogin-backend-toolbaritems-statustoolbaritem .dropdown-menu";
  MultisiteBeloginSelector["status"] = "#wapplersystems-multisitebelogin-backend-toolbaritems-statustoolbaritem .multisite-belogin-module-status";
  MultisiteBeloginSelector["data"] = "[data-systeminformation-data]";
  MultisiteBeloginSelector["badge"] = "[data-systeminformation-badge]";
  MultisiteBeloginSelector["message"] = "[data-systeminformation-message-module]";
  MultisiteBeloginSelector["messageLink"] = "[data-systeminformation-message-module] a";
})(MultisiteBeloginSelector || (MultisiteBeloginSelector = {}));

/**
 */
class MultisiteBeloginStatusMenu {
  constructor() {
    this.timer = null;
    this.updateMenu = () => {

      document.querySelector(MultisiteBeloginSelector.status).innerHTML = '';

      const toolbarItemIcon = document.querySelector(MultisiteBeloginSelector.icon);
      const currentIcon = toolbarItemIcon.cloneNode(true);
      if (this.timer !== null) {
        clearTimeout(this.timer);
        this.timer = null;
      }
      Icons.getIcon('spinner-circle-light', Icons.sizes.small).then((spinner) => {
        toolbarItemIcon.replaceWith(document.createRange().createContextualFragment(spinner));
      });

      (new AjaxRequest(TYPO3.settings.ajaxUrls.multisitebelogin_sites)).get().then(async (response) => {
        let sites = await response.resolve();
        const divSiteList = document.createElement('ul');
        divSiteList.classList.add('dropdown-list');
        divSiteList.classList.add('w-100');
        document.querySelector(MultisiteBeloginSelector.status).appendChild(divSiteList);

        for (const [pageUid, site] of Object.entries(sites)) {
          const divSite = document.createElement('li');
          divSite.classList.add('dropdown-item');

          const divSiteWrapper = document.createElement('div');
          divSiteWrapper.classList.add('d-flex');
          divSiteWrapper.classList.add('flex-column');
          divSiteWrapper.classList.add('w-100');
          divSiteWrapper.innerHTML = '<div><span >' + site['websiteTitle'] + '</span></div>';
          divSite.appendChild(divSiteWrapper);
          divSiteList.appendChild(divSite);

          const divSiteLanguages = document.createElement('div');
          divSiteLanguages.classList.add('site-languages');
          divSiteWrapper.appendChild(divSiteLanguages);

          for (const [key, siteLanguage] of Object.entries(site['siteLanguages'])) {

            const divSiteLanguage = document.createElement('div');
            divSiteLanguage.classList.add('site-language');
            divSiteLanguage.dataset.host = siteLanguage['protocol'] + '://' + siteLanguage['host'];
            divSiteLanguage.dataset.base = siteLanguage['base'];
            divSiteLanguage.dataset.active = 'false';
            divSiteLanguages.appendChild(divSiteLanguage);
          }

        }

        let currentHost = window.location.protocol + '//' + window.location.hostname;

        let identicalHosts = document.querySelectorAll('.site-language[data-host="' + currentHost + '"]');
        identicalHosts.forEach(function (element) {
          element.dataset.active = 'true';
          element.classList.add('active');
        });

        let foreignHosts = [];
        let foreignHostSites = document.querySelectorAll('.site-language:not([data-host="' + currentHost + '"])');
        foreignHostSites.forEach(function (element) {
          foreignHosts.push(element.dataset.host);
        });
        const uniqueHosts = [...new Set(foreignHosts)];


        (new AjaxRequest(TYPO3.settings.ajaxUrls.multisitebelogin_token_generate)).get().then(async (response) => {
          let e = await response.resolve();
          let token = e.token;
          let username = e.username;

          uniqueHosts.forEach(function (host) {
            let ok = false;

            (new AjaxRequest(host + TYPO3.settings.ajaxUrls.multisitebelogin_status)).get({
              credentials: 'include'
            }).then(async (response) => {

              let status = await response.resolve();

              if (response.response.status == 200 && status.login.timed_out == false) {
                ok = true;
                let hostElements = document.querySelectorAll('.site-language[data-host="' + host + '"]');
                hostElements.forEach(function (element) {
                  element.dataset.active = 'true';
                });
              }

            }).finally(() => {
              if (!ok) {

                (new AjaxRequest(host + TYPO3.settings.ajaxUrls.multisitebelogin_login)).post({
                  login_status: 'login',
                  msblToken: token,
                  username: username
                },{
                  credentials: 'include'
                }).then(async (response) => {
                  let loginStatus = await response.resolve();
                  if (loginStatus.login.success) {
                    let hostElements = document.querySelectorAll('.site-language[data-host="' + host + '"]');
                    hostElements.forEach(function (element) {
                      element.dataset.active = 'true';
                    });
                  }

                });

              }

            });

          });

        });


      }).finally(() => {
        document.querySelector(MultisiteBeloginSelector.icon).replaceWith(currentIcon);

        // reload error data every five minutes
        this.timer = setTimeout(this.updateMenu, 1000 * 300);
      });
    };
    new RegularEvent('click', this.handleMessageLinkClick)
      .delegateTo(document, MultisiteBeloginSelector.messageLink);
    Viewport.Topbar.Toolbar.registerEvent(this.updateMenu);
  }

  static getData() {
    const element = document.querySelector(MultisiteBeloginSelector.data);
    const data = element.dataset;
    return {
      count: data.systeminformationDataCount ? parseInt(data.systeminformationDataCount, 10) : 0,
      severityBadgeClass: data.systeminformationDataSeveritybadgeclass ?? '',
    };
  }

  static getMessageDataFromElement(element) {
    const data = element.dataset;
    return {
      count: data.systeminformationMessageCount ? parseInt(data.systeminformationMessageCount, 10) : 0,
      status: data.systeminformationMessageStatus ?? '',
      module: data.systeminformationMessageModule ?? '',
      params: data.systeminformationMessageParams ?? '',
    };
  }

  static updateBadge() {
    const data = MultisiteBeloginStatusMenu.getData();
    const element = document.querySelector(MultisiteBeloginSelector.badge);
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

  /**
   * Updates the UC and opens the linked module
   */
  handleMessageLinkClick(event, target) {
    const messageData = MultisiteBeloginStatusMenu.getMessageDataFromElement(target.closest(MultisiteBeloginSelector.message));
    if (messageData.module === '') {
      return;
    }
    event.preventDefault();
    event.stopPropagation();
    const moduleStorageObject = {};
    const timestamp = Math.floor(Date.now() / 1000);
    let storedMultisiteBeloginSettings = {};
    if (PersistentStorage.isset('systeminformation')) {
      storedMultisiteBeloginSettings = JSON.parse(PersistentStorage.get('systeminformation'));
    }
    moduleStorageObject[messageData.module] = {lastAccess: timestamp};
    Object.assign(storedMultisiteBeloginSettings, moduleStorageObject);
    const ajax = PersistentStorage.set('systeminformation', JSON.stringify(storedMultisiteBeloginSettings));
    ajax.then(() => {
      // finally, open the module now
      TYPO3.ModuleMenu.App.showModule(messageData.module, messageData.params);
      Viewport.Topbar.refresh();
    });
  }
}

export default new MultisiteBeloginStatusMenu();
