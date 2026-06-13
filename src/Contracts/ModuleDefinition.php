<?php

namespace Coolsam\Modules\Contracts;

interface ModuleDefinition
{
    public function name(): string;

    public function alias(): string;

    public function path(): string;

    /**
     * @return list<string>
     */
    public function dependencies(): array;

    /**
     * @return array<string, mixed>
     */
    public function manifest(): array;
}
