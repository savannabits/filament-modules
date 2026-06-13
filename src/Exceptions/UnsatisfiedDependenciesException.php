<?php

namespace Coolsam\Modules\Exceptions;

use Exception;

class UnsatisfiedDependenciesException extends Exception
{
    /**
     * @param  list<string>  $missing
     */
    public function __construct(
        public readonly string $module,
        public readonly array $missing,
    ) {
        parent::__construct(
            "Cannot activate module [{$module}]. Missing or inactive dependencies: " . implode(', ', $missing) . '.'
        );
    }
}
