<?php

namespace Coolsam\FilamentModules\Commands;

use Illuminate\Console\Command;

class ModulesCommand extends Command
{
    public $signature = 'modules';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
