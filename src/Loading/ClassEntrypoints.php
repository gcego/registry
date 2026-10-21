<?php

declare(strict_types=1);

namespace Gcego\Registry\Loading;

use PhpToken;

/**
 * Process-wide bookkeeping for definition files that declare classes.
 *
 * PHP can declare a class once per process, so such a file is included at
 * most once: its return value is remembered and reused by later loads
 * (refresh(), cache rebuilds, verification, long-running workers). Files that
 * declare no classes are not tracked and are included on every load.
 *
 * State is static because class declarations are process-wide too; it is
 * keyed by real path and never shared between processes.
 */
final class ClassEntrypoints
{
    /**
     * @var array<string, array{signature: string, classes: list<string>}>
     */
    private static array $declarations = [];

    /**
     * @var array<string, array{signature: string, result: mixed}>
     */
    private static array $results = [];

    /**
     * Fully qualified names of the classes, interfaces, traits and enums the file declares.
     *
     * @return list<string>
     */
    public static function declaredIn(string $realPath): array
    {
        $signature = self::signature($realPath);

        if ((self::$declarations[$realPath]['signature'] ?? null) === $signature) {
            return self::$declarations[$realPath]['classes'];
        }

        $code = @file_get_contents($realPath);
        $classes = $code === false ? [] : self::parse($code);
        self::$declarations[$realPath] = ['signature' => $signature, 'classes' => $classes];

        return $classes;
    }

    /**
     * Whether a class-like symbol is already declared (never autoloads).
     */
    public static function isDeclared(string $class): bool
    {
        return class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false) || enum_exists($class, false);
    }

    public static function remember(string $realPath, mixed $result): void
    {
        self::$results[$realPath] = ['signature' => self::signature($realPath), 'result' => $result];
    }

    public static function hasResult(string $realPath): bool
    {
        return isset(self::$results[$realPath]);
    }

    public static function result(string $realPath): mixed
    {
        return self::$results[$realPath]['result'] ?? null;
    }

    /**
     * Whether the file changed since its result was remembered (or first observed).
     */
    public static function changedSinceLoaded(string $realPath): bool
    {
        $known = self::$results[$realPath]['signature'] ?? null;

        return $known !== null && $known !== self::signature($realPath);
    }

    /**
     * Record a file whose classes were declared by something else (such as
     * Composer's autoloader), so later changes to it are detected.
     */
    public static function observe(string $realPath, mixed $result): void
    {
        if (! isset(self::$results[$realPath])) {
            self::remember($realPath, $result);
        }
    }

    /**
     * Forget everything. Declared classes stay declared; for test isolation only.
     *
     * @internal
     */
    public static function reset(): void
    {
        self::$declarations = [];
        self::$results = [];
    }

    private static function signature(string $realPath): string
    {
        clearstatcache(true, $realPath);
        $stat = @stat($realPath);

        return $stat === false ? 'missing' : $stat['size'].'|'.$stat['mtime'].'|'.$stat['ino'];
    }

    /**
     * @return list<string>
     */
    private static function parse(string $code): array
    {
        $tokens = array_values(array_filter(
            PhpToken::tokenize($code),
            static fn (PhpToken $token): bool => ! $token->isIgnorable(),
        ));
        $namespace = '';
        $classes = [];

        foreach ($tokens as $index => $token) {
            if ($token->is(T_NAMESPACE)) {
                $next = $tokens[$index + 1] ?? null;
                $namespace = $next !== null && $next->is([T_STRING, T_NAME_QUALIFIED]) ? $next->text : '';

                continue;
            }

            if (! $token->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM])) {
                continue;
            }

            $previous = $tokens[$index - 1] ?? null;
            $name = $tokens[$index + 1] ?? null;

            // Foo::class is a constant, and anonymous classes ("new class (...) {") have no name.
            if ($previous?->is(T_DOUBLE_COLON) === true || $name === null || ! $name->is(T_STRING)) {
                continue;
            }

            $classes[] = ltrim($namespace.'\\'.$name->text, '\\');
        }

        return array_values(array_unique($classes));
    }
}
