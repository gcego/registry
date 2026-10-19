<?php

declare(strict_types=1);

namespace Gcego\Registry\Console;

use Gcego\Registry\RegistryManager;
use Illuminate\Console\Command;

/**
 * Builds the registry manifest. Runs as part of "php artisan optimize".
 */
final class CacheRegistryCommand extends Command
{
    protected $signature = 'gcego:registry-cache';

    protected $description = 'Discover, validate and cache GCEGO definitions';

    public function handle(RegistryManager $manager): int
    {
        $manager->clear();
        $loaded = $manager->rebuild();

        $this->components->info(sprintf('GCEGO registry cached: %d definition(s).', $loaded->index->count()));

        return self::SUCCESS;
    }
}
