<?php

declare(strict_types=1);

namespace Gcego\Registry\Resolution;

use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Blueprints\Contracts\BlueprintResolver;
use Gcego\Registry\Exceptions\DefinitionNotFoundException;
use Gcego\Registry\Kinds\BlueprintKind;
use Gcego\Registry\Registry;

/**
 * Exposes the registry's unscoped blueprints through gcego/blueprints' BlueprintResolver contract.
 *
 * Bound in the container, so runtimes can depend on BlueprintResolver alone.
 */
final readonly class RegistryBlueprintResolver implements BlueprintResolver
{
    public function __construct(private Registry $registry) {}

    public function has(string $name): bool
    {
        return $this->registry->has(BlueprintKind::Id.':'.$name);
    }

    /**
     * @throws DefinitionNotFoundException
     */
    public function resolve(string $name): BlueprintInterface
    {
        return $this->registry->blueprint($name);
    }

    public function names(): array
    {
        return array_values(array_map(
            static fn ($descriptor): string => $descriptor->name(),
            $this->registry->descriptors(BlueprintKind::Id, ''),
        ));
    }
}
