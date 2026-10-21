<?php

declare(strict_types=1);

namespace Gcego\Registry\Cache;

use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\Registration\RegistrationResult;
use ReflectionClass;

/**
 * Application code outside the discovery roots that shaped the index:
 * definition and provider classes, kinds and their contracts, and any file
 * included while building. Their content hashes are stored in the manifest so
 * verification notices edits the discovery fingerprint cannot see.
 *
 * Files under a "vendor" directory are left out: they only change with
 * "composer install", and deployments rebuild the cache afterwards.
 */
final class DependencyFiles
{
    /**
     * @param  list<string>  $includedBefore  get_included_files() taken before the build.
     * @param  list<class-string>  $providers
     * @param  list<string>  $discovered  Real paths already covered by the discovery fingerprint.
     * @return array<string, string> Real path => content hash, sorted by path.
     */
    public static function collect(array $includedBefore, RegistrationResult $result, array $providers, DefinitionKinds $kinds, array $discovered): array
    {
        $classes = [...$providers];

        foreach ($result->index as $descriptor) {
            $classes[] = $descriptor->class;
        }

        foreach ($kinds->ids() as $id) {
            $kind = $kinds->get($id);

            if ($kind !== null) {
                $classes[] = $kind::class;
                $classes[] = $kind->contract();
            }
        }

        $paths = array_diff(get_included_files(), $includedBefore);

        foreach (array_unique($classes) as $class) {
            if (class_exists($class, false) || interface_exists($class, false)) {
                /** @var class-string $class */
                $paths[] = (string) (new ReflectionClass($class))->getFileName();
            }
        }

        $skip = array_flip($discovered);
        $hashes = [];

        foreach ($paths as $path) {
            $real = realpath($path);

            if ($real === false || isset($skip[$real]) || isset($hashes[$real]) || self::isVendor($real)) {
                continue;
            }

            $hashes[$real] = self::hash($real);
        }

        ksort($hashes, SORT_STRING);

        return $hashes;
    }

    /**
     * @param  array<string, string>  $hashes
     */
    public static function unchanged(array $hashes): bool
    {
        foreach ($hashes as $path => $hash) {
            if (! hash_equals($hash, self::hash($path))) {
                return false;
            }
        }

        return true;
    }

    public static function hash(string $path): string
    {
        $hash = is_file($path) ? @hash_file('xxh128', $path) : false;

        return $hash === false ? 'missing' : $hash;
    }

    private static function isVendor(string $path): bool
    {
        return str_contains(str_replace('\\', '/', $path), '/vendor/');
    }
}
