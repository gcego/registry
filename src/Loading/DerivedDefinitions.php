<?php

declare(strict_types=1);

namespace Gcego\Registry\Loading;

use Gcego\Registry\Contracts\DerivesDefinitions;
use Gcego\Registry\Exceptions\DefinitionLoadException;
use Throwable;

/**
 * Expands definitions that derive further definitions (DerivesDefinitions),
 * depth first, for loading, cache builds and runtime registration alike.
 */
final class DerivedDefinitions
{
    /** How deep derived definitions may derive further definitions. */
    public const int MaxDepth = 3;

    /**
     * The definitions, each followed by what it derives.
     *
     * @param  list<object>  $definitions
     * @return list<object>
     *
     * @throws DefinitionLoadException
     */
    public static function expand(array $definitions, string $source, int $depth = 0): array
    {
        $expanded = [];

        foreach ($definitions as $definition) {
            $expanded[] = $definition;

            if (! $definition instanceof DerivesDefinitions) {
                continue;
            }

            if ($depth >= self::MaxDepth) {
                throw DefinitionLoadException::invalidReturn($source, 'definitions derived more than '.self::MaxDepth.' levels deep');
            }

            try {
                $derived = [];

                foreach ($definition->derivedDefinitions() as $child) {
                    if (! is_object($child) || $child === $definition) {
                        throw DefinitionLoadException::invalidReturn($source, 'a derived '.get_debug_type($child));
                    }

                    $derived[] = $child;
                }
            } catch (DefinitionLoadException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                throw DefinitionLoadException::failed($source, $exception);
            }

            $expanded = [...$expanded, ...self::expand($derived, $source, $depth + 1)];
        }

        return $expanded;
    }
}
