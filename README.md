
# multisite-belogin
TYPO3 Extension for automatic cross domain backend login. The editor now can use the backend tools in frontend like preview.

## What does it do?
This extension allows editors to be automatically logged in to the backend when they access frontends with other domains.
This is particularly useful for previewing content and using backend tools directly from the frontend without having to log in separately for each domain.

## Installation

* Install the extension via composer/TER
* Add the "refresh" GET parameter to the [FE][cacheHash][excludedParameters] configuration in your system settings
* You must log in to backend with https
* Run the database schema update after installing or updating (`vendor/bin/typo3 extension:setup -e multisite_belogin`), the login tokens are kept in the table `tx_multisitebelogin_token`

Every session backend of the TYPO3 core is supported, the database backend as well as Redis.
