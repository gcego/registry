<?php

declare(strict_types=1);

namespace Gcego\Registry\Console;

use Gcego\Registry\RegistryManager;
use Illuminate\Console\Command;

/**
 * Removes the registry manifest. Runs as part of "php artisan optimize:clear".
 */
final class ClearRegistryCommand extends Command
{
    protected $signature = 'gcego:registry-clear';

    protected $description = 'Remove the cached GCEGO definition registry';

    public function handle(RegistryManager $manager): int
    {
        $manager->clear();
        $this->components->info('GCEGO registry cache cleared.');

        return self::SUCCESS;
    }
}
