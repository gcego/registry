<?php

declare(strict_types=1);

namespace Gcego\Registry\Loading;

use Gcego\Registry\Contracts\DefinitionLoader;
use Gcego\Registry\Contracts\DefinitionProvider;
use Gcego\Registry\Exceptions\DefinitionLoadException;
use Gcego\Registry\Index\DefinitionSource;
use Gcego\Registry\Index\SourceType;
use Illuminate\Contracts\Container\Container;
use ReflectionClass;
use Throwable;

/**
 * Loads trusted PHP definition files and DefinitionProvider classes.
 *
 * Definition files are application code: including them is not a sandbox.
 * This loader therefore only includes files whose real path lies inside a
 * configured discovery root, never paths from requests or remote sources.
 *
 * A file must return either a definition object or the class name of a
 * DefinitionProvider (resolved through the container). A file that declares
 * a DefinitionProvider class may instead return nothing ("class entrypoint").
 * Files are included with `require` in an isolated static scope, without
 * changing the working directory; any output is treated as an error.
 *
 * Files that declare classes are included at most once per process (see
 * ClassEntrypoints): later loads reuse the first result, and a file already
 * loaded by Composer's autoloader is not included again. Files that declare
 * no classes are included on every load, so they always return fresh objects.
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
        $classes = ClassEntrypoints::declaredIn($real);
        $result = $classes === [] ? $this->include($real, $source) : $this->includeClassEntrypoint($real, $source, $classes);

        if ($result === 1 && $classes !== []) {
            return $this->loadProvider($this->implicitProvider($real, $source, $classes), $source, $expand);
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

                $definitions[] = $definition;
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

    /**
     * Include a file in an isolated static scope; output is an error.
     *
     * @throws DefinitionLoadException
     */
    private function include(string $real, string $source): mixed
    {
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

        return $result;
    }

    /**
     * Include a class-declaring file at most once per process and reuse its
     * return value afterwards. When its classes were declared by something
     * else (Composer's autoloader), the file is not included again.
     *
     * @param  list<string>  $classes
     *
     * @throws DefinitionLoadException
     */
    private function includeClassEntrypoint(string $real, string $source, array $classes): mixed
    {
        $declared = array_values(array_filter($classes, ClassEntrypoints::isDeclared(...)));

        if ($declared === []) {
            $result = $this->include($real, $source);
            ClassEntrypoints::remember($real, $result);

            return $result;
        }

        foreach ($declared as $class) {
            /** @var class-string $class */
            $file = (new ReflectionClass($class))->getFileName();

            if ($file === false || realpath($file) !== $real) {
                throw DefinitionLoadException::classConflict($real, $class, $file === false ? 'PHP itself' : $file);
            }
        }

        if (ClassEntrypoints::changedSinceLoaded($real)) {
            throw DefinitionLoadException::reloadRequired($real);
        }

        if (! ClassEntrypoints::hasResult($real)) {
            // Declared elsewhere: the file cannot run again, so treat it as a class entrypoint.
            ClassEntrypoints::observe($real, 1);
        }

        return ClassEntrypoints::result($real);
    }

    /**
     * The single DefinitionProvider class a class entrypoint without a return value declares.
     *
     * @param  list<string>  $classes
     *
     * @throws DefinitionLoadException
     */
    private function implicitProvider(string $real, string $source, array $classes): string
    {
        $providers = array_values(array_filter($classes, static fn (string $class): bool => is_subclass_of($class, DefinitionProvider::class)));

        if ($providers === []) {
            throw DefinitionLoadException::invalidReturn($source, 'int');
        }

        if (count($providers) > 1) {
            throw DefinitionLoadException::ambiguousClassEntrypoint($real, count($providers));
        }

        return $providers[0];
    }

    public function isTrusted(string $realPath): bool
    {
        foreach ($this->trustedRoots as $root) {
            $directory = realpath($root);

            if ($directory !== false && is_dir($directory) && str_starts_with($realPath, rtrim($directory, '\\/').DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }
}
