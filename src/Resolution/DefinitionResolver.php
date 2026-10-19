<?php

declare(strict_types=1);

namespace Gcego\Registry\Resolution;

use Gcego\Registry\Contracts\DefinitionLoader;
use Gcego\Registry\Exceptions\DefinitionLoadException;
use Gcego\Registry\Exceptions\InvalidDefinitionException;
use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Registry\Kinds\DefinitionKinds;

/**
 * Loads the definition object behind a descriptor (lazy resolution).
 *
 * The loaded object must still be what the index recorded: same kind, same
 * name and an instance of the recorded class. Otherwise the index is stale.
 */
final readonly class DefinitionResolver
{
    public function __construct(
        private DefinitionLoader $loader,
        private DefinitionKinds $kinds,
        private bool $validate = true,
    ) {}

    /**
     * @throws DefinitionLoadException|InvalidDefinitionException
     */
    public function resolve(DefinitionDescriptor $descriptor): object
    {
        $kind = $this->kinds->get($descriptor->kind())
            ?? throw InvalidDefinitionException::unknownKind($descriptor->kind(), $descriptor->source->toString());

        foreach ($this->loader->load($descriptor->source) as $definition) {
            if (! is_a($definition, $kind->contract()) || ! $definition instanceof $descriptor->class) {
                continue;
            }

            try {
                $name = $kind->name($definition);
            } catch (\Throwable) {
                continue;
            }

            if ($name !== $descriptor->name()) {
                continue;
            }

            if ($this->validate) {
                $errors = $kind->validate($definition);

                if ($errors !== []) {
                    throw InvalidDefinitionException::failedValidation($descriptor->key(), $descriptor->source->toString(), $errors);
                }
            }

            return $definition;
        }

        throw DefinitionLoadException::stale($descriptor->key(), $descriptor->source->toString());
    }
}
