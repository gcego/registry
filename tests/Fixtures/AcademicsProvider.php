<?php

declare(strict_types=1);

namespace Gcego\Registry\Tests\Fixtures;

use Gcego\Registry\Contracts\DefinitionProvider;
use Illuminate\Contracts\Config\Repository;

/**
 * A class-based provider using constructor injection.
 */
final readonly class AcademicsProvider implements DefinitionProvider
{
    public function __construct(private Repository $config) {}

    public function definitions(): iterable
    {
        $title = $this->config->get('app.name');

        yield new ExampleDefinition('topics', is_string($title) ? 'Topics of '.$title : 'Topics');
        yield new ExampleDefinition('lessons');
    }
}
