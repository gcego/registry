<?php

declare(strict_types=1);

namespace Gcego\Registry\Cache;

use Gcego\Registry\Exceptions\RegistryCacheException;
use Gcego\Registry\Exceptions\RegistryException;
use Gcego\Registry\Index\DefinitionIndex;

/**
 * The compiled registry: descriptors only (scalars), plus integrity data.
 *
 * Stored shape: ['format', 'generated_at', 'fingerprint', 'definitions', 'checksum'].
 * The checksum covers format, fingerprint and definitions.
 */
final readonly class Manifest
{
    public const int Format = 1;

    public function __construct(
        public DefinitionIndex $index,
        public string $fingerprint,
        public string $generatedAt,
    ) {}

    /**
     * @return array{format: int, generated_at: string, fingerprint: string, definitions: list<array<string, mixed>>, checksum: string}
     */
    public function toArray(): array
    {
        $definitions = $this->index->toArray();

        return [
            'format' => self::Format,
            'generated_at' => $this->generatedAt,
            'fingerprint' => $this->fingerprint,
            'definitions' => $definitions,
            'checksum' => self::checksum(self::Format, $this->fingerprint, $definitions),
        ];
    }

    /**
     * @throws RegistryCacheException
     */
    public static function fromArray(mixed $data, string $path): self
    {
        if (! is_array($data)) {
            throw RegistryCacheException::corrupt($path, 'not an array');
        }

        if (($data['format'] ?? null) !== self::Format) {
            throw RegistryCacheException::corrupt($path, 'unsupported format');
        }

        $fingerprint = $data['fingerprint'] ?? null;
        $generatedAt = $data['generated_at'] ?? null;
        $definitions = $data['definitions'] ?? null;
        $checksum = $data['checksum'] ?? null;

        if (! is_string($fingerprint) || ! is_string($generatedAt) || ! is_array($definitions) || ! array_is_list($definitions) || ! is_string($checksum)) {
            throw RegistryCacheException::corrupt($path, 'missing fields');
        }

        if (! hash_equals(self::checksum(self::Format, $fingerprint, $definitions), $checksum)) {
            throw RegistryCacheException::corrupt($path, 'checksum mismatch');
        }

        try {
            $index = DefinitionIndex::fromArray($definitions);
        } catch (RegistryException $exception) {
            throw RegistryCacheException::corrupt($path, 'invalid definitions', $exception);
        }

        return new self($index, $fingerprint, $generatedAt);
    }

    /**
     * @param  array<mixed>  $definitions
     */
    private static function checksum(int $format, string $fingerprint, array $definitions): string
    {
        return hash('sha256', json_encode([$format, $fingerprint, $definitions], JSON_THROW_ON_ERROR));
    }
}
