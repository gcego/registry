<?php

declare(strict_types=1);

namespace Gcego\Registry\Discovery;

use Gcego\Registry\Exceptions\DiscoveryException;
use Gcego\Registry\Identity\DefinitionIdentity;

/**
 * One configured discovery root: a directory, the kind of definitions in it,
 * its entrypoint convention and an optional scope.
 */
final readonly class DiscoveryPath
{
    public function __construct(
        public string $path,
        public string $kind,
        public Entrypoint $entrypoint,
        public ?string $scope = null,
        public bool $required = false,
    ) {}

    /**
     * @param  array<mixed>  $config  ['path' => ..., 'kind' => ..., 'entrypoint' => ..., 'scope' => ..., 'required' => ...]
     *
     * @throws DiscoveryException
     */
    public static function fromConfig(int|string $index, array $config): self
    {
        $path = $config['path'] ?? null;
        $kind = $config['kind'] ?? null;
        $entrypoint = $config['entrypoint'] ?? Entrypoint::File;
        $scope = $config['scope'] ?? null;
        $required = $config['required'] ?? false;

        if (! is_string($path) || $path === '') {
            throw DiscoveryException::invalidRoot($index, '"path" must be a non-empty string.');
        }

        if (! is_string($kind) || preg_match(DefinitionIdentity::KindPattern, $kind) !== 1) {
            throw DiscoveryException::invalidRoot($index, '"kind" must be a definition kind identifier such as "module".');
        }

        if (! is_string($entrypoint)) {
            throw DiscoveryException::invalidRoot($index, '"entrypoint" must be a string.');
        }

        if ($scope !== null && (! is_string($scope) || preg_match(DefinitionIdentity::ScopePattern, $scope) !== 1)) {
            throw DiscoveryException::invalidRoot($index, '"scope" must be lowercase dotted identifiers such as "admin".');
        }

        if (! is_bool($required)) {
            throw DiscoveryException::invalidRoot($index, '"required" must be a boolean.');
        }

        return new self(rtrim($path, '/\\'), $kind, Entrypoint::fromConfig($entrypoint, $index), $scope, $required);
    }

    /**
     * @return array{path: string, kind: string, entrypoint: string, scope: string|null, required: bool}
     */
    public function toArray(): array
    {
        return ['path' => $this->path, 'kind' => $this->kind, 'entrypoint' => $this->entrypoint->strategy, 'scope' => $this->scope, 'required' => $this->required];
    }
}
