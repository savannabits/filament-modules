<?php

namespace Coolsam\Modules\Exceptions;

use Exception;

class ModuleIsDependedUponException extends Exception
{
    /**
     * @param  list<string>  $dependents
     */
    public function __construct(
        public readonly string $module,
        public readonly array $dependents,
    ) {
        parent::__construct(
            "Cannot deactivate module [{$module}]. Active dependents: " . implode(', ', $dependents) . '.'
        );
    }
}
