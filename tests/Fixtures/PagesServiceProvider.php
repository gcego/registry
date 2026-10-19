<?php

declare(strict_types=1);

namespace Gcego\Registry\Tests\Fixtures;

use Gcego\Registry\Kinds\DefinitionKinds;
use Illuminate\Support\ServiceProvider;

/**
 * Registers a definition kind the way docs/extensions.md recommends.
 */
final class PagesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(DefinitionKinds::class)->register(new PageKind);
    }
}
