<?php

namespace Coolsam\Modules\Commands\FileGenerators;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Commands\FileGenerators\ClassGenerator;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Str;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\Method;

class ModulePanelProviderClassGenerator extends ClassGenerator
{
    public ?\Nwidart\Modules\Module $module;

    final public function __construct(
        protected string $fqn,
        protected string $id,
        protected string $moduleName,
        protected bool $isDefault = false,
    ) {
        $this->module = \Module::find($this->moduleName);
        if (! $this->module) {
            throw new \InvalidArgumentException("Module '{$this->moduleName}' not found.");
        }
    }

    public function getNamespace(): string
    {
        return $this->extractNamespace($this->getFqn());
    }

    /**
     * @return array<string>
     */
    public function getImports(): array
    {
        return [
            Panel::class,
            $this->getExtends(),
            Color::class,
            Dashboard::class,
            AccountWidget::class,
            FilamentInfoWidget::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            AuthenticateSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            DisableBladeIconComponents::class,
            DispatchServingFilamentEvent::class,
            Authenticate::class,
        ];
    }

    public function getBasename(): string
    {
        return class_basename($this->getFqn());
    }

    public function getExtends(): string
    {
        return PanelProvider::class;
    }

    protected function addMethodsToClass(ClassType $class): void
    {
        $this->addPanelMethodToClass($class);
    }

    public function getModule(): \Nwidart\Modules\Module
    {
        return $this->module;
    }

    protected function addPanelMethodToClass(ClassType $class): void
    {
        $method = $class->addMethod('panel')
            ->setPublic()
            ->setReturnType(Panel::class)
            ->setBody($this->generatePanelMethodBody());
        $method->addParameter('panel')
            ->setType(Panel::class);

        $this->configurePanelMethod($method);
    }

    public function generatePanelMethodBody(): string
    {
        $isDefault = $this->isDefault();

        $defaultOutput = $isDefault
            ? <<<'PHP'

                    ->default()
                PHP
            : '';

        $loginOutput = $isDefault
            ? <<<'PHP'

                    ->login()
                PHP
            : '';

        $id = str($this->getId())->kebab()->lower()->toString();
        $panelId = str($id)->prepend('-')->prepend($this->getModule()->getKebabName())->toString();
        $urlPath = str($id)->prepend('/')->prepend($this->getModule()->getKebabName())->toString();
        $label = $this->getModule()->getTitle() . ' ' . str($id)->studly()->snake()->title()->replace(['_', '-'], ' ')->toString();
        $componentsDirectory = Str::studly($panelId);
        $componentsNamespace = (Str::studly($panelId) . '\\');

        $rootNamespace = str($this->getModule()->namespace())->rtrim('\\')->append('\\')->toString();
        $moduleName = $this->getModule()->getName();

        return new Literal(
            <<<PHP
                \$separator = DIRECTORY_SEPARATOR;
                return \$panel{$defaultOutput}
                    ->id(?)
                    ->path(?){$loginOutput}
                    ->brandName("$label")
                    ->colors([
                        'primary' => {$this->simplifyFqn(Color::class)}::Amber,
                    ])
                    ->discoverResources(in: module("$moduleName", true)->appPath("Filament{\$separator}{$componentsDirectory}{\$separator}Resources"), for: module("$moduleName", true)->appNamespace('Filament\\{$componentsNamespace}Resources'))
                    ->discoverPages(in:module("$moduleName", true)->appPath("Filament{\$separator}{$componentsDirectory}{\$separator}Pages"), for: module("$moduleName", true)->appNamespace('Filament\\{$componentsNamespace}Pages'))
                    ->pages([
                        {$this->simplifyFqn(Dashboard::class)}::class,
                    ])
                    ->discoverWidgets(in:module("$moduleName", true)->appPath("Filament{\$separator}{$componentsDirectory}{\$separator}Widgets"), for: module("$moduleName", true)->appNamespace('Filament\\{$componentsNamespace}Widgets'))
                    ->widgets([
                        {$this->simplifyFqn(AccountWidget::class)}::class,
                        {$this->simplifyFqn(FilamentInfoWidget::class)}::class,
                    ])
                    ->discoverClusters(in: module("$moduleName", true)->appPath("Filament{\$separator}{$componentsDirectory}{\$separator}Clusters"), for: module("$moduleName", true)->appNamespace('Filament\\{$componentsNamespace}Clusters'))
                    ->middleware([
                        {$this->simplifyFqn(EncryptCookies::class)}::class,
                        {$this->simplifyFqn(AddQueuedCookiesToResponse::class)}::class,
                        {$this->simplifyFqn(StartSession::class)}::class,
                        {$this->simplifyFqn(AuthenticateSession::class)}::class,
                        {$this->simplifyFqn(ShareErrorsFromSession::class)}::class,
                        {$this->simplifyFqn(VerifyCsrfToken::class)}::class,
                        {$this->simplifyFqn(SubstituteBindings::class)}::class,
                        {$this->simplifyFqn(DisableBladeIconComponents::class)}::class,
                        {$this->simplifyFqn(DispatchServingFilamentEvent::class)}::class,
                    ])
                    ->authMiddleware([
                        {$this->simplifyFqn(Authenticate::class)}::class,
                    ])->navigationItems([
                        // Add a backlink to the default panel
                        {$this->simplifyFqn(NavigationItem::class)}::make()
                            ->label(__('Back Home'))
                            ->sort(-1000)
                            ->icon(\Filament\Support\Icons\Heroicon::OutlinedHomeModern)
                            ->url(filament()->getDefaultPanel()->getUrl()),
                    ]);
                PHP,
            [$panelId, $urlPath],
        );
    }

    protected function configurePanelMethod(Method $method): void {}

    public function getFqn(): string
    {
        return $this->fqn;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }
}
