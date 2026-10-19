<?php

declare(strict_types=1);

namespace Gcego\Registry\Index;

enum SourceType: string
{
    /**
     * A discovered PHP file that returns a definition (or a provider class name).
     */
    case File = 'file';

    /**
     * A DefinitionProvider class listed in configuration.
     */
    case Provider = 'provider';

    /**
     * An object registered at runtime with Registry::register(); never cached.
     */
    case Runtime = 'runtime';
}
