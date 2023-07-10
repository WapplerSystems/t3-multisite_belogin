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

var __importDefault = this && this.__importDefault || function (e) {
  return e && e.__esModule ? e : {default: e}
};
define(["require", "exports", "jquery", "TYPO3/CMS/Core/Ajax/AjaxRequest", "TYPO3/CMS/Backend/Icons", "TYPO3/CMS/Backend/Notification", "TYPO3/CMS/Backend/Storage/Persistent", "TYPO3/CMS/Backend/Viewport"], (function (e, t, jquery, AjaxRequest, Icons, Notification, PersistentStorage, Viewport) {
  "use strict";
  var MultisiteBeloginSelector;
  jquery = __importDefault(jquery),
  function (e) {
    e.element = "#wapplersystems-multisitebelogin-backend-toolbaritems-statustoolbaritem",
    e.icon = "#wapplersystems-multisitebelogin-backend-toolbaritems-statustoolbaritem .toolbar-item-icon .t3js-icon",
    e.menu = "#wapplersystems-multisitebelogin-backend-toolbaritems-statustoolbaritem .dropdown-menu"
    e.status = "#wapplersystems-multisitebelogin-backend-toolbaritems-statustoolbaritem .multisite-belogin-module-status"
  }(MultisiteBeloginSelector || (MultisiteBeloginSelector = {}));

  class Sites {

    constructor() {
      this.timer = null;
      this.updateMenu = () => {

        if (document.querySelector(MultisiteBeloginSelector.status) === null) {
          return;
        }

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

      Viewport.Topbar.Toolbar.registerEvent(this.updateMenu);
    }


  }
  return new Sites
}));

