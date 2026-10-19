<?php

declare(strict_types=1);

namespace Gcego\Registry\Tests\Fixtures;

use Gcego\Blueprints\Blueprint;
use Gcego\Components\Property;
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

        yield new Blueprint('topics', new Property(Blueprint::Title, is_string($title) ? 'Topics of '.$title : 'Topics'));
        yield new Blueprint('lessons');
    }
}
