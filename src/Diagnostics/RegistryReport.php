<?php

declare(strict_types=1);

namespace Gcego\Registry\Diagnostics;

/**
 * The structured output of RegistryInspector, for future CLI and health tooling.
 */
final readonly class RegistryReport
{
    /**
     * @param  list<RegistryDiagnostic>  $diagnostics
     * @param  array<string, int>  $countsByKind
     * @param  list<array{path: string, kind: string, entrypoint: string, scope: string|null, exists: bool, kindRegistered: bool}>  $roots
     * @param  array<string, mixed>  $cache
     */
    public function __construct(
        public array $diagnostics,
        public array $countsByKind,
        public array $roots,
        public array $cache,
    ) {}

    /**
     * @return list<RegistryDiagnostic>
     */
    public function withLevel(DiagnosticLevel $level): array
    {
        return array_values(array_filter($this->diagnostics, static fn (RegistryDiagnostic $diagnostic): bool => $diagnostic->level === $level));
    }

    /**
     * @return list<RegistryDiagnostic>
     */
    public function check(string $check): array
    {
        return array_values(array_filter($this->diagnostics, static fn (RegistryDiagnostic $diagnostic): bool => $diagnostic->check === $check));
    }

    public function isHealthy(): bool
    {
        return $this->withLevel(DiagnosticLevel::Error) === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'healthy' => $this->isHealthy(),
            'countsByKind' => $this->countsByKind,
            'roots' => $this->roots,
            'cache' => $this->cache,
            'diagnostics' => array_map(static fn (RegistryDiagnostic $diagnostic): array => $diagnostic->toArray(), $this->diagnostics),
        ];
    }
}
