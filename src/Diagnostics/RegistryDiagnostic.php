<?php

declare(strict_types=1);

namespace Gcego\Registry\Diagnostics;

/**
 * One structured registry finding.
 */
final readonly class RegistryDiagnostic
{
    /**
     * @param  array<string, scalar|list<scalar>|null>  $details
     */
    public function __construct(
        public string $check,
        public DiagnosticLevel $level,
        public string $message,
        public array $details = [],
    ) {}

    /**
     * @return array{check: string, level: string, message: string, details: array<string, scalar|list<scalar>|null>}
     */
    public function toArray(): array
    {
        return ['check' => $this->check, 'level' => $this->level->value, 'message' => $this->message, 'details' => $this->details];
    }
}
