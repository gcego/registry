<?php

declare(strict_types=1);

namespace Gcego\Registry\Contracts;

use Gcego\Registry\Exceptions\DefinitionLoadException;
use Gcego\Registry\Index\DefinitionSource;

/**
 * Turns a definition source into the definition objects it declares.
 */
interface DefinitionLoader
{
    /**
     * @return list<object>
     *
     * @throws DefinitionLoadException
     */
    public function load(DefinitionSource $source): array;
}
