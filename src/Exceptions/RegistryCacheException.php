<?php

declare(strict_types=1);

namespace Gcego\Registry\Exceptions;

use RuntimeException;
use Throwable;

class RegistryCacheException extends RuntimeException implements RegistryException
{
    public static function corrupt(string $path, string $reason, ?Throwable $previous = null): self
    {
        return new self(sprintf('The registry cache %s is corrupt (%s). Rebuild it with "php artisan gcego:registry-cache".', $path, $reason), 0, $previous);
    }

    public static function missing(string $path): self
    {
        return new self(sprintf(
            'The registry cache %s does not exist and gcego-registry.cache.missing is "fail". Build it during deployment with "php artisan gcego:registry-cache".',
            $path,
        ));
    }

    public static function unwritable(string $path, ?Throwable $previous = null): self
    {
        return new self(sprintf('The registry cache %s could not be written. Make its directory writable.', $path), 0, $previous);
    }

    public static function unlockable(string $path): self
    {
        return new self(sprintf('The registry cache lock %s could not be acquired. Keep the cache on a filesystem that supports flock().', $path));
    }
}
