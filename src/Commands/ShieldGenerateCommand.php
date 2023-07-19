<?php

namespace Savannabits\FilamentModules\Commands;

use BezhanSalleh\FilamentShield\Commands\MakeShieldGenerateCommand;
use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Support\Collection;
use Nwidart\Modules\Module;
use Str;

class ShieldGenerateCommand extends MakeShieldGenerateCommand
{
    protected function generateForPages(array $pages): Collection
    {
        collect(\Module::all())->each(function (Module $module) {
            $permission = Utils::getPermissionModel()::firstOrCreate(
                ['name' => Str::of($module->getLowerName())->prepend('module_')->toString()],
                ['guard_name' => Utils::getFilamentAuthGuard()]
            )->name;
        });
        return parent::generateForPages($pages);
    }
    protected function generatePolicyPath(array $entity): string
    {
        $path = (new \ReflectionClass($entity['fqcn']::getModel()))->getFileName();

        if (\Illuminate\Support\Str::of($path)->contains(['vendor', 'src'])) {
            $basePolicyPath = app_path(
                (string) Str::of($entity['model'])
                    ->prepend('Policies\\')
                    ->replace('\\', DIRECTORY_SEPARATOR),
            );

            return "{$basePolicyPath}Policy.php";
        }

        /** @phpstan-ignore-next-line */
        return Str::of($path)
            ->replace('Models', 'Policies')
            ->replace('Entities', 'Policies')
            ->replaceLast('.php', 'Policy.php')
            ->replace('\\', DIRECTORY_SEPARATOR);
    }
    protected function generatePolicyStubVariables(array $entity): array
    {
        $stubVariables = collect(Utils::getResourcePermissionPrefixes($entity['fqcn']))
            ->reduce(function ($gates, $permission) use ($entity) {
                $gates[\Illuminate\Support\Str::studly($permission)] = $permission.'_'.$entity['resource'];

                return $gates;
            }, collect())->toArray();

        $stubVariables['auth_model_fqcn'] = Utils::getAuthProviderFQCN();
        $stubVariables['auth_model_name'] = Str::of($stubVariables['auth_model_fqcn'])->afterLast('\\');
        $stubVariables['auth_model_variable'] = Str::of($stubVariables['auth_model_name'])->camel();

        $reflectionClass = new \ReflectionClass($entity['fqcn']::getModel());
        $namespace = $reflectionClass->getNamespaceName();
        $path = $reflectionClass->getFileName();

        $stubVariables['namespace'] = Str::of($path)->contains(['vendor', 'src'])
            ? 'App\Policies'
            : Str::of($namespace)
                ->replace('Models', 'Policies')
                ->replace('Entities', 'Policies')
        ; /** @phpstan-ignore-line */
        $stubVariables['model_name'] = $entity['model'];
        $stubVariables['model_fqcn'] = $namespace.'\\'.$entity['model'];
        $stubVariables['model_variable'] = Str::of($entity['model'])->camel();
        $stubVariables['modelPolicy'] = "{$entity['model']}Policy";

        return $stubVariables;
    }
}
