<?php

declare(strict_types=1);

namespace Netlogix\Nxgooglelocations\Backend\Template;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;

/**
 * This factory class is nearly a carbon copy of the TYPO3\CMS\Backend\Template\ModuleTemplateFactory
 * with the only difference that it allows to pass package names to the BackendViewFactory.
 */
#[Autoconfigure(public: true, shared: false)]
final readonly class ModuleTemplateFactory
{
    public function __construct(
        protected PageRenderer $pageRenderer,
        protected IconFactory $iconFactory,
        protected UriBuilder $uriBuilder,
        protected ModuleProvider $moduleProvider,
        protected FlashMessageService $flashMessageService,
        protected ExtensionConfiguration $extensionConfiguration,
        protected BackendViewFactory $viewFactory,
    ) {}

    public function create(ServerRequestInterface $request, array $packagesNames = []): ModuleTemplate
    {
        return new ModuleTemplate(
            $this->pageRenderer,
            $this->iconFactory,
            $this->uriBuilder,
            $this->moduleProvider,
            $this->flashMessageService,
            $this->extensionConfiguration,
            $this->viewFactory->create($request, $packagesNames),
            $request,
        );
    }
}
