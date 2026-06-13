<?php

namespace Coolsam\Modules\Contracts;

interface TenantContext
{
    public function resolve(): string | int | null;
}
