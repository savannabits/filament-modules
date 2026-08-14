<?php
/*
 *          M""""""""`M            dP
 *          Mmmmmm   .M            88
 *          MMMMP  .MMM  dP    dP  88  .dP   .d8888b.
 *          MMP  .MMMMM  88    88  88888"    88'  `88
 *          M' .MMMMMMM  88.  .88  88  `8b.  88.  .88
 *          M         M  `88888P'  dP   `YP  `88888P'
 *          MMMMMMMMMMM    -*-  Created by Zuko  -*-
 *
 *          * * * * * * * * * * * * * * * * * * * * *
 *          * -    - -   F.R.E.E.M.I.N.D   - -    - *
 *          * -  Copyright © 2026 (Z) Programing  - *
 *          *    -  -  All Rights Reserved  -  -    *
 *          * * * * * * * * * * * * * * * * * * * * *
 */

namespace Coolsam\Modules\Support;

use Composer\Autoload\ClassLoader;
use Nwidart\Modules\Module as NwidartModule;

/**
 * Resolves namespaces and class names for modules without assuming that every
 * module lives under `config('modules.namespace')`.
 *
 * A module namespace is data, not a convention: projects routinely host modules
 * from several vendors (e.g. `Acme\Crm\Billing` next to `Modules\Blog`), so the
 * sources of truth are consulted in order of reliability:
 *
 *  1. the file itself (`namespace X;` + the declared class name),
 *  2. Composer's runtime PSR-4 map (the map PHP actually autoloads with),
 *  3. the module's own `composer.json` autoload.psr-4 section,
 *  4. `config('modules.namespace')` as the legacy fallback.
 */
class NamespaceResolver
{
    /** @var array<int, array{prefix: string, dir: string}>|null */
    protected ?array $psr4Prefixes = null;

    /** @var array<string, string|null> */
    protected array $classCache = [];

    /** @var array<string, string> */
    protected array $moduleNamespaceCache = [];

    /**
     * Resolve the fully qualified class name a path maps to.
     *
     * Works for files that do not exist yet (generators), in which case the
     * answer comes from the PSR-4 maps rather than from the file contents.
     */
    public function resolveClass(string $path): ?string
    {
        $key = $this->normalizePath($path);

        if (array_key_exists($key, $this->classCache)) {
            return $this->classCache[$key];
        }

        $class = $this->classFromFile($path)
            ?? $this->classFromComposerPsr4($path)
            ?? $this->classFromModuleComposerJson($path)
            ?? $this->classFromModulesConfig($path);

        return $this->classCache[$key] = $class;
    }

    /**
     * The base namespace of a module, optionally suffixed with a relative one.
     */
    public function moduleNamespace(NwidartModule $module, string $relativeNamespace = ''): string
    {
        $key = $this->normalizePath($module->getPath());

        $base = $this->moduleNamespaceCache[$key] ??= $this->resolveModuleBaseNamespace($module);

        $relativeNamespace = trim(str_replace('/', '\\', $relativeNamespace), '\\');

        return $relativeNamespace === '' ? $base : $base . '\\' . $relativeNamespace;
    }

    /**
     * Composer's PSR-4 prefixes, longest directory first so that the most
     * specific mapping wins.
     *
     * @return array<int, array{prefix: string, dir: string}>
     */
    public function composerPsr4Prefixes(): array
    {
        if ($this->psr4Prefixes !== null) {
            return $this->psr4Prefixes;
        }

        return $this->psr4Prefixes = $this->sortPrefixes($this->normalizePrefixMap($this->composerPsr4Map()));
    }

    public function flush(): void
    {
        $this->psr4Prefixes = null;
        $this->classCache = [];
        $this->moduleNamespaceCache = [];
    }

    public function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));

        if ($path === '') {
            return '';
        }

        $real = @realpath($path);

        if ($real !== false) {
            return rtrim(str_replace('\\', '/', $real), '/');
        }

        return rtrim($this->resolveRelativeSegments($path), '/');
    }

    /**
     * Read the namespace and the declared type name straight from the file.
     */
    protected function classFromFile(string $path): ?string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        if ($contents === false || $contents === '') {
            return null;
        }

        $tokens = @token_get_all($contents);
        $namespace = null;
        $name = null;

        foreach ($tokens as $index => $token) {
            if (! is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE && $namespace === null) {
                $namespace = $this->readNamespaceName($tokens, $index);

                continue;
            }

            if (! in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                continue;
            }

            if (! $this->isTypeDeclaration($tokens, $index)) {
                continue;
            }

            $name = $this->readTokenAfter($tokens, $index);

            if ($name !== null) {
                break;
            }
        }

        if ($namespace === null && $name === null) {
            return null;
        }

        $name ??= basename($path, '.php');

        return $namespace === null || $namespace === '' ? $name : $namespace . '\\' . $name;
    }

    /**
     * Map the path onto Composer's PSR-4 prefixes. This is the only strategy
     * that works for files which have not been written yet.
     */
    protected function classFromComposerPsr4(string $path): ?string
    {
        return $this->matchPrefixes($this->composerPsr4Prefixes(), $path);
    }

    /**
     * Fall back to the `composer.json` shipped with the module owning the path,
     * for modules that were added without a `composer dump-autoload`.
     */
    protected function classFromModuleComposerJson(string $path): ?string
    {
        $moduleDirectory = $this->findModuleDirectory($path);

        if ($moduleDirectory === null) {
            return null;
        }

        return $this->matchPrefixes($this->modulePsr4Prefixes($moduleDirectory), $path);
    }

    /**
     * Legacy behaviour: derive the namespace from `config('modules.namespace')`.
     */
    protected function classFromModulesConfig(string $path): ?string
    {
        $modulesPath = $this->normalizePath((string) config('modules.paths.modules', base_path('Modules')));
        $normalized = $this->normalizePath($path);

        if ($modulesPath === '' || ! $this->pathStartsWith($normalized, $modulesPath . '/')) {
            return null;
        }

        $relative = substr($normalized, strlen($modulesPath) + 1);
        $segments = array_values(array_filter(explode('/', $relative), 'strlen'));

        if ($segments === []) {
            return null;
        }

        $appFolder = trim(str_replace('\\', '/', (string) config('modules.paths.app_folder', 'app')), '/');

        // Drop the app folder segment: `<module>/app/Filament/X` -> `<module>/Filament/X`.
        if ($appFolder !== '' && isset($segments[1]) && strcasecmp($segments[1], $appFolder) === 0) {
            unset($segments[1]);
            $segments = array_values($segments);
        }

        $last = array_key_last($segments);
        $segments[$last] = $this->stripPhpExtension($segments[$last]);

        return trim((string) config('modules.namespace', 'Modules'), '\\') . '\\' . implode('\\', $segments);
    }

    protected function resolveModuleBaseNamespace(NwidartModule $module): string
    {
        $appPath = $this->normalizePath($module->getAppPath());
        $modulePath = $this->normalizePath($module->getPath());

        return $this->namespaceForDirectory($this->composerPsr4Prefixes(), $appPath, $modulePath)
            ?? $this->namespaceForDirectory($this->modulePsr4Prefixes($modulePath), $appPath, $modulePath)
            // Packages that map their root namespace onto a sub-directory
            // (`"Acme\\Blog\\": "src/"`) rather than onto the app folder.
            ?? $this->namespaceForModuleSubdirectory($this->composerPsr4Prefixes(), $modulePath)
            ?? $this->namespaceForModuleSubdirectory($this->modulePsr4Prefixes($modulePath), $modulePath)
            ?? $this->namespaceFromModuleSource($module, $appPath)
            ?? trim((string) config('modules.namespace', 'Modules'), '\\') . '\\' . $module->getStudlyName();
    }

    /**
     * Find a PSR-4 prefix that maps exactly onto the module's app directory
     * (or onto the module root when there is no app folder).
     *
     * @param  array<int, array{prefix: string, dir: string}>  $prefixes
     */
    protected function namespaceForDirectory(array $prefixes, string $appPath, string $modulePath): ?string
    {
        foreach ([$appPath, $modulePath] as $directory) {
            if ($directory === '') {
                continue;
            }

            foreach ($prefixes as $prefix) {
                if ($this->pathsMatch($prefix['dir'], $directory)) {
                    return $prefix['prefix'];
                }
            }
        }

        return null;
    }

    /**
     * Find the PSR-4 root that sits closest to the module root, for modules
     * whose namespace is mapped onto a sub-directory such as `src/`.
     *
     * @param  array<int, array{prefix: string, dir: string}>  $prefixes
     */
    protected function namespaceForModuleSubdirectory(array $prefixes, string $modulePath): ?string
    {
        if ($modulePath === '') {
            return null;
        }

        $best = null;

        foreach ($prefixes as $prefix) {
            if (! $this->pathStartsWith($prefix['dir'], $modulePath . '/')) {
                continue;
            }

            if ($best === null || strlen($prefix['dir']) < strlen($best['dir'])) {
                $best = $prefix;
            }
        }

        return $best['prefix'] ?? null;
    }

    /**
     * Last resort before the legacy config: read the namespace out of a PHP
     * file inside the module and subtract its sub-namespace.
     */
    protected function namespaceFromModuleSource(NwidartModule $module, string $appPath): ?string
    {
        if ($appPath === '' || ! is_dir($appPath)) {
            return null;
        }

        $candidates = array_merge(
            (array) glob($appPath . '/Providers/' . $module->getStudlyName() . '*Provider.php'),
            (array) glob($appPath . '/Providers/*.php'),
            (array) glob($appPath . '/*.php'),
            (array) glob($appPath . '/*/*.php'),
        );

        foreach (array_filter($candidates) as $candidate) {
            $class = $this->classFromFile($candidate);

            if ($class === null || ! str_contains($class, '\\')) {
                continue;
            }

            // `<app>/Providers/CoreServiceProvider.php` declares
            // `MyLink\Cerm\Core\Providers` -> strip one segment per sub-folder.
            $relative = substr($this->normalizePath($candidate), strlen($appPath) + 1);
            $depth = count(array_filter(explode('/', dirname($relative)), fn ($segment) => $segment !== '' && $segment !== '.'));

            $segments = explode('\\', substr($class, 0, (int) strrpos($class, '\\')));

            if ($depth > 0) {
                if (count($segments) <= $depth) {
                    continue;
                }

                $segments = array_slice($segments, 0, count($segments) - $depth);
            }

            $namespace = implode('\\', $segments);

            if ($namespace !== '') {
                return $namespace;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{prefix: string, dir: string}>  $prefixes
     */
    protected function matchPrefixes(array $prefixes, string $path): ?string
    {
        $normalized = $this->stripPhpExtension($this->normalizePath($path));

        if ($normalized === '') {
            return null;
        }

        foreach ($prefixes as $prefix) {
            if ($prefix['dir'] === '' || ! $this->pathStartsWith($normalized, $prefix['dir'] . '/')) {
                continue;
            }

            $relative = substr($normalized, strlen($prefix['dir']) + 1);

            return trim($prefix['prefix'] . '\\' . str_replace('/', '\\', $relative), '\\');
        }

        return null;
    }

    /**
     * PSR-4 prefixes declared by a module's own composer.json.
     *
     * @return array<int, array{prefix: string, dir: string}>
     */
    protected function modulePsr4Prefixes(string $moduleDirectory): array
    {
        $composerFile = $moduleDirectory . '/composer.json';

        if (! is_file($composerFile)) {
            return [];
        }

        $composer = json_decode((string) file_get_contents($composerFile), true);
        $map = is_array($composer) ? ($composer['autoload']['psr-4'] ?? []) : [];

        if (! is_array($map) || $map === []) {
            return [];
        }

        $prefixes = [];

        foreach ($map as $prefix => $targets) {
            foreach ((array) $targets as $target) {
                $target = trim(str_replace('\\', '/', (string) $target), '/');
                $directory = $target === '' || $target === '.'
                    ? $moduleDirectory
                    : $moduleDirectory . '/' . $target;

                $prefixes[] = ['prefix' => trim((string) $prefix, '\\'), 'dir' => $this->normalizePath($directory)];
            }
        }

        return $this->sortPrefixes($prefixes);
    }

    /**
     * Walk up from a path until the directory that owns it is found.
     */
    protected function findModuleDirectory(string $path): ?string
    {
        $directory = $this->normalizePath($path);

        if ($directory !== '' && is_file($path)) {
            $directory = dirname($directory);
        }

        $modulesPath = $this->normalizePath((string) config('modules.paths.modules', base_path('Modules')));

        while ($directory !== '' && $directory !== '/' && $this->pathStartsWith($directory, $modulesPath)) {
            if (is_file($directory . '/module.json') || is_file($directory . '/composer.json')) {
                return $directory;
            }

            $parent = dirname($directory);

            if ($parent === $directory) {
                break;
            }

            $directory = $parent;
        }

        return null;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    protected function composerPsr4Map(): array
    {
        foreach (spl_autoload_functions() ?: [] as $autoloader) {
            $loader = is_array($autoloader) ? ($autoloader[0] ?? null) : null;

            if ($loader instanceof ClassLoader) {
                return $loader->getPrefixesPsr4();
            }
        }

        $generated = base_path('vendor/composer/autoload_psr4.php');

        if (is_file($generated)) {
            $map = require $generated;

            if (is_array($map)) {
                return $map;
            }
        }

        return [];
    }

    /**
     * @param  array<string, array<int, string>|string>  $map
     * @return array<int, array{prefix: string, dir: string}>
     */
    protected function normalizePrefixMap(array $map): array
    {
        $prefixes = [];

        foreach ($map as $prefix => $directories) {
            $prefix = trim((string) $prefix, '\\');

            if ($prefix === '') {
                continue;
            }

            foreach ((array) $directories as $directory) {
                $directory = $this->normalizePath((string) $directory);

                if ($directory !== '') {
                    $prefixes[] = ['prefix' => $prefix, 'dir' => $directory];
                }
            }
        }

        return $prefixes;
    }

    /**
     * @param  array<int, array{prefix: string, dir: string}>  $prefixes
     * @return array<int, array{prefix: string, dir: string}>
     */
    protected function sortPrefixes(array $prefixes): array
    {
        usort($prefixes, fn (array $a, array $b) => strlen($b['dir']) <=> strlen($a['dir']));

        return $prefixes;
    }

    /**
     * @param  array<int, array{0: int, 1: string}|string>  $tokens
     */
    protected function readNamespaceName(array $tokens, int $index): string
    {
        $name = '';

        for ($i = $index + 1, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token === ';' || $token === '{') {
                break;
            }

            if (! is_array($token)) {
                continue;
            }

            if (in_array($token[0], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED], true)) {
                $name .= $token[1];
            }
        }

        return trim($name, '\\');
    }

    /**
     * @param  array<int, array{0: int, 1: string}|string>  $tokens
     */
    protected function readTokenAfter(array $tokens, int $index): ?string
    {
        for ($i = $index + 1, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return is_array($token) && $token[0] === T_STRING ? $token[1] : null;
        }

        return null;
    }

    /**
     * Tell a type declaration apart from `Foo::class` and `new class {}`.
     *
     * @param  array<int, array{0: int, 1: string}|string>  $tokens
     */
    protected function isTypeDeclaration(array $tokens, int $index): bool
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            $token = $tokens[$i];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if (! is_array($token)) {
                return true;
            }

            return ! in_array($token[0], [T_DOUBLE_COLON, T_NEW, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true);
        }

        return true;
    }

    protected function stripPhpExtension(string $path): string
    {
        return preg_replace('/\.php$/i', '', $path) ?? $path;
    }

    /**
     * Resolve `.` and `..` segments for paths that do not exist on disk.
     */
    protected function resolveRelativeSegments(string $path): string
    {
        $prefix = match (true) {
            str_starts_with($path, '//') => '//', // UNC share
            str_starts_with($path, '/') => '/',
            default => '',
        };

        $resolved = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($resolved);

                continue;
            }

            $resolved[] = $segment;
        }

        return $prefix . implode('/', $resolved);
    }

    protected function pathsMatch(string $first, string $second): bool
    {
        return $this->isCaseInsensitiveFilesystem()
            ? strcasecmp($first, $second) === 0
            : $first === $second;
    }

    protected function pathStartsWith(string $path, string $prefix): bool
    {
        if ($prefix === '') {
            return false;
        }

        return $this->isCaseInsensitiveFilesystem()
            ? strncasecmp($path, $prefix, strlen($prefix)) === 0
            : str_starts_with($path, $prefix);
    }

    protected function isCaseInsensitiveFilesystem(): bool
    {
        return DIRECTORY_SEPARATOR === '\\';
    }
}
