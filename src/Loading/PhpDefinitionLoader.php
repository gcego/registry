<?php

declare(strict_types=1);

namespace Gcego\Registry\Loading;

use Gcego\Components\Contracts\Buildable;
use Gcego\Registry\Contracts\DefinitionLoader;
use Gcego\Registry\Contracts\DefinitionProvider;
use Gcego\Registry\Exceptions\DefinitionLoadException;
use Gcego\Registry\Index\DefinitionSource;
use Gcego\Registry\Index\SourceType;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Loads trusted PHP definition files and DefinitionProvider classes.
 *
 * Definition files are application code: including them is not a sandbox.
 * This loader therefore only includes files whose real path lies inside a
 * configured discovery root, never paths from requests or remote sources.
 *
 * A file must return either a definition object or the class name of a
 * DefinitionProvider (resolved through the container). Files are included
 * with `require` in an isolated static scope, without changing the working
 * directory; any output is treated as an error.
 */
final readonly class PhpDefinitionLoader implements DefinitionLoader
{
    /**
     * @param  list<string>  $trustedRoots  Directories files may be loaded from. They are resolved at
     *                                      load time, so a root created after start-up is trusted too.
     */
    public function __construct(
        private Container $container,
        private array $trustedRoots,
    ) {}

    public function load(DefinitionSource $source): array
    {
        return match ($source->type) {
            SourceType::File => $this->loadFile($source->location),
            SourceType::Provider => $this->loadProvider($source->location),
            SourceType::Runtime => throw DefinitionLoadException::invalidReturn($source->toString(), 'a runtime registration, which cannot be reloaded'),
        };
    }

    /**
     * The file's definitions, followed by what they derive unless $expand is false.
     *
     * @return list<object>
     *
     * @throws DefinitionLoadException
     */
    public function loadFile(string $path, bool $expand = true): array
    {
        $real = realpath($path);

        if ($real === false || ! is_file($real)) {
            throw DefinitionLoadException::missingFile($path);
        }

        if (! $this->isTrusted($real)) {
            throw DefinitionLoadException::untrustedPath($real);
        }

        $source = 'file '.$real;
        $level = ob_get_level();
        ob_start();

        try {
            $result = (static fn (string $__gcegoDefinitionFile): mixed => require $__gcegoDefinitionFile)($real);
        } catch (Throwable $exception) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw DefinitionLoadException::failed($source, $exception);
        }

        $output = '';

        while (ob_get_level() > $level) {
            $output = ob_get_clean().$output;
        }

        if ($output !== '') {
            throw DefinitionLoadException::output($real);
        }

        if ($result instanceof Buildable) {
            $result = $result->build();
        }

        if (is_string($result)) {
            return $this->loadProvider($result, $source, $expand);
        }

        if (! is_object($result)) {
            throw DefinitionLoadException::invalidReturn($source, get_debug_type($result));
        }

        return $expand ? $this->expand([$result], $source) : [$result];
    }

    /**
     * @return list<object>
     *
     * @throws DefinitionLoadException
     */
    public function loadProvider(string $class, ?string $via = null, bool $expand = true): array
    {
        if (! class_exists($class) || ! is_subclass_of($class, DefinitionProvider::class)) {
            throw DefinitionLoadException::invalidProvider($class);
        }

        $source = $via ?? 'provider '.$class;

        try {
            $provider = $this->container->make($class);

            if (! $provider instanceof DefinitionProvider) {
                throw DefinitionLoadException::invalidProvider($class);
            }

            $definitions = [];

            foreach ($provider->definitions() as $definition) {
                if (! is_object($definition)) {
                    throw DefinitionLoadException::invalidReturn($source, get_debug_type($definition));
                }

                $definitions[] = $definition instanceof Buildable ? $definition->build() : $definition;
            }
        } catch (DefinitionLoadException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw DefinitionLoadException::failed($source, $exception);
        }

        return $expand ? $this->expand($definitions, $source) : $definitions;
    }

    /**
     * @param  list<object>  $definitions
     * @return list<object>
     *
     * @throws DefinitionLoadException
     */
    public function expand(array $definitions, string $source): array
    {
        return DerivedDefinitions::expand($definitions, $source);
    }

    public function isTrusted(string $realPath): bool
    {
        foreach ($this->trustedRoots as $root) {
            $directory = realpath($root);

            if ($directory !== false && is_dir($directory) && str_starts_with($realPath, rtrim($directory, '/').'/')) {
                return true;
            }
        }

        return false;
    }
}
