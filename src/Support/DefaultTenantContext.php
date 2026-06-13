<?php

namespace Coolsam\Modules\Support;

use Coolsam\Modules\Contracts\TenantContext;

class DefaultTenantContext implements TenantContext
{
    public function resolve(): string | int | null
    {
        return null;
    }
}
