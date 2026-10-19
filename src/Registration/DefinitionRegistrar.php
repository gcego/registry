<?php

declare(strict_types=1);

namespace Gcego\Registry\Registration;

use Gcego\Registry\Contracts\DefinitionKind;
use Gcego\Registry\Discovery\DiscoveryResult;
use Gcego\Registry\Exceptions\DuplicateDefinitionException;
use Gcego\Registry\Exceptions\InvalidDefinitionException;
use Gcego\Registry\Exceptions\RegistryException;
use Gcego\Registry\Identity\DefinitionIdentity;
use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Registry\Index\DefinitionIndex;
use Gcego\Registry\Index\DefinitionSource;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\Loading\DerivedDefinitions;
use Gcego\Registry\Loading\PhpDefinitionLoader;
use Throwable;

/**
 * Builds a DefinitionIndex from discovered entrypoints and provider classes.
 *
 * Every definition is loaded once, checked against its kind's contract,
 * named by its kind, and validated by its kind (which delegates to
 * gcego/blueprints / gcego/components). In strict mode the first problem
 * throws; otherwise problems are collected and the source is skipped.
 */
final readonly class DefinitionRegistrar
{
    public function __construct(
        private DefinitionKinds $kinds,
        private PhpDefinitionLoader $loader,
    ) {}

    /**
     * @param  list<class-string>  $providers
     *
     * @throws RegistryException in strict mode.
     */
    public function build(DiscoveryResult $discovery, array $providers = [], bool $strict = true): RegistrationResult
    {
        /** @var array<string, DefinitionDescriptor> $descriptors */
        $descriptors = [];
        $definitions = [];
        $failures = [];
        $unsupported = [];

        $register = function (string $sourceLabel, callable $produce) use (&$descriptors, &$definitions, &$failures, $strict): void {
            try {
                /** @var list<array{DefinitionDescriptor, object}> $entries */
                $entries = $produce();

                foreach ($entries as [$descriptor, $definition]) {
                    $key = $descriptor->key();

                    if (isset($descriptors[$key]) && ! $descriptors[$key]->isSameRegistration($descriptor)) {
                        throw DuplicateDefinitionException::forIdentity($key, $descriptors[$key]->source->toString(), $descriptor->source->toString());
                    }

                    $descriptors[$key] = $descriptor;
                    $definitions[$key] = $definition;
                }
            } catch (RegistryException $exception) {
                if ($strict) {
                    throw $exception;
                }

                $failures[] = new RegistrationFailure($sourceLabel, $exception);
            }
        };

        foreach ($discovery->files as $file) {
            $kind = $this->kinds->get($file->root->kind);

            if ($kind === null) {
                $unsupported[] = ['path' => $file->path, 'kind' => $file->root->kind];

                continue;
            }

            $source = DefinitionSource::file($file->path);
            $register($source->toString(), fn (): array => $this->describeAll($this->loader->loadFile($file->path, false), $kind, $source, $file->root->scope));
        }

        foreach ($providers as $provider) {
            $source = DefinitionSource::provider($provider);
            $register($source->toString(), fn (): array => $this->describeAll($this->loader->loadProvider($provider, null, false), null, $source, null));
        }

        return new RegistrationResult(new DefinitionIndex(...array_values($descriptors)), $definitions, $failures, $unsupported);
    }

    /**
     * Describe loaded definitions ($kind: the discovery root's, or null to
     * match by contract) and the definitions they derive (always matched by contract).
     *
     * @param  list<object>  $definitions
     * @return list<array{DefinitionDescriptor, object}>
     *
     * @throws RegistryException
     */
    private function describeAll(array $definitions, ?DefinitionKind $kind, DefinitionSource $source, ?string $scope): array
    {
        $entries = [];

        foreach ($definitions as $definition) {
            $entries[] = [$this->describe($definition, $kind ?? $this->kindFor($definition, $source), $source, $scope), $definition];

            foreach (DerivedDefinitions::expand([$definition], $source->toString()) as $derived) {
                if ($derived !== $definition) {
                    $entries[] = [$this->describe($derived, $this->kindFor($derived, $source), $source, $scope), $derived];
                }
            }
        }

        return $entries;
    }

    /**
     * Check, name, validate and describe one definition.
     *
     * @throws InvalidDefinitionException
     */
    public function describe(object $definition, DefinitionKind $kind, DefinitionSource $source, ?string $scope): DefinitionDescriptor
    {
        if (! is_a($definition, $kind->contract())) {
            throw InvalidDefinitionException::contractMismatch($kind->id(), $kind->contract(), $definition::class, $source->toString());
        }

        try {
            $name = $kind->name($definition);
        } catch (Throwable $exception) {
            throw InvalidDefinitionException::unnamed($kind->id(), $source->toString(), $exception);
        }

        $identity = new DefinitionIdentity($kind->id(), $name, $scope);
        $errors = $kind->validate($definition);

        if ($errors !== []) {
            throw InvalidDefinitionException::failedValidation($identity->toString(), $source->toString(), $errors);
        }

        return new DefinitionDescriptor($identity, $source, $definition::class, $kind->metadata($definition));
    }

    /**
     * @throws InvalidDefinitionException when no kind, or several kinds, match.
     */
    public function kindFor(object $definition, DefinitionSource $source): DefinitionKind
    {
        $matching = $this->kinds->matching($definition);

        if (count($matching) === 1) {
            return $matching[0];
        }

        if ($matching === []) {
            throw InvalidDefinitionException::noKind($definition::class, $source->toString(), $this->kinds->ids());
        }

        throw InvalidDefinitionException::ambiguousKind($definition::class, $source->toString(), array_map(static fn (DefinitionKind $kind): string => $kind->id(), $matching));
    }
}
