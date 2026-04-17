<?php

namespace WapplerSystems\MultisiteBelogin\EventListener;

use TYPO3\CMS\Backend\Routing\Event\AfterPagePreviewUriGeneratedEvent;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\BackendEntryPointResolver;

final class AfterPagePreviewUriGeneratedEventListener
{

    public function __construct(
        protected readonly BackendEntryPointResolver $backendEntryPointResolver,
        private readonly UriBuilder $uriBuilder
    ) {}

    public function __invoke(AfterPagePreviewUriGeneratedEvent $event): void
    {
        /** @var ServerRequest $request */
        $request = $GLOBALS['TYPO3_REQUEST'];

        $previewUri = $event->getPreviewUri();

        $backendRedirectUri = $this->generateBackendUrl('multisitebelogin_redirect');
        $redirectUrl = Uri::fromAnyScheme($backendRedirectUri);
        $redirectUrl = $redirectUrl
            ->withScheme($request->getUri()->getScheme())
            ->withHost($request->getUri()->getHost())
            ->withPort($request->getUri()->getPort())
            ->withQuery($redirectUrl->getQuery(). '&url=' . urlencode($previewUri->__toString()));

        $event->setPreviewUri($redirectUrl);
    }

    protected function generateBackendUrl(string $route): string
    {
        return (string)$this->uriBuilder->buildUriFromRoute($route);
    }
}
