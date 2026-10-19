<?php

declare(strict_types=1);

namespace Gcego\Registry\Registration;

use Throwable;

/**
 * A source that could not be registered (recorded when the registry is not strict).
 */
final readonly class RegistrationFailure
{
    public function __construct(
        public string $source,
        public Throwable $exception,
    ) {}

    /**
     * @return array{source: string, exception: class-string<Throwable>, message: string}
     */
    public function toArray(): array
    {
        return ['source' => $this->source, 'exception' => $this->exception::class, 'message' => $this->exception->getMessage()];
    }
}
