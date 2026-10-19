<?php

declare(strict_types=1);

namespace Gcego\Registry\Tests\Fixtures;

/**
 * Counts definition file executions, to prove when files are (not) loaded.
 */
final class LoadCounter
{
    /**
     * @var array<string, int>
     */
    private static array $hits = [];

    public static function hit(string $file): void
    {
        self::$hits[$file] = (self::$hits[$file] ?? 0) + 1;
    }

    public static function total(): int
    {
        return array_sum(self::$hits);
    }

    public static function reset(): void
    {
        self::$hits = [];
    }
}
