<?php

declare(strict_types=1);

/**
 * Repeatable benchmark for gcego/registry (no Laravel boot).
 *
 * Usage: composer benchmark  (or: php benchmarks/run.php [definitions] [lookups])
 *
 * Generates N module definition files (each with a helper file the
 * convention must skip) in a temporary directory, then measures discovery,
 * a full build (load + validate), manifest write/read, identity lookups,
 * kind filtering and lazy resolution from the manifest.
 */

use Gcego\Registry\Cache\FileRegistryCache;
use Gcego\Registry\Cache\Manifest;
use Gcego\Registry\Discovery\DefinitionDiscovery;
use Gcego\Registry\Discovery\DiscoveryPath;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\Loading\PhpDefinitionLoader;
use Gcego\Registry\Registration\DefinitionRegistrar;
use Gcego\Registry\Resolution\DefinitionResolver;
use Gcego\Registry\Tests\Fixtures\ExampleKind;
use Illuminate\Container\Container;

require __DIR__.'/../vendor/autoload.php';

$count = max(1, (int) ($argv[1] ?? 500));
$lookups = max(1, (int) ($argv[2] ?? 100000));
$root = sys_get_temp_dir().'/gcego-registry-bench-'.bin2hex(random_bytes(4));

for ($index = 0; $index < $count; $index++) {
    $directory = sprintf('%s/Modules/Group%d/Feature%d', $root, $index % 20, $index);
    mkdir($directory, 0777, true);
    file_put_contents($directory.'/Feature'.$index.'.php', "<?php\n\nreturn new Gcego\\Registry\\Tests\\Fixtures\\ExampleDefinition('feature-{$index}');\n");
    file_put_contents($directory.'/Table.php', "<?php\n\nthrow new RuntimeException('helper files are never loaded');\n");
}

$measure = static function (string $label, Closure $operation, int $operations = 1): void {
    $start = hrtime(true);
    $operation();
    $elapsed = (hrtime(true) - $start) / 1e6;

    $operations === 1
        ? printf("%-38s %10.3f ms\n", $label, $elapsed)
        : printf("%-38s %10.3f ms total, %.3f us/op\n", $label, $elapsed, $elapsed * 1000 / $operations);
};

$kinds = new DefinitionKinds;
$kinds->register(new ExampleKind);
$roots = [DiscoveryPath::fromConfig(0, ['path' => $root.'/Modules', 'kind' => 'module', 'entrypoint' => 'directory'])];
$discovery = new DefinitionDiscovery($roots);
$loader = new PhpDefinitionLoader(new Container, [$root.'/Modules']);
$registrar = new DefinitionRegistrar($kinds, $loader);
$cache = new FileRegistryCache($root.'/cache/gcego-registry.php');

printf("gcego/registry benchmark: %d definitions (+%d helper files), PHP %s, opcache %s\n\n", $count, $count, PHP_VERSION, function_exists('opcache_get_status') && is_array(@opcache_get_status(false)) ? 'on' : 'off');

$discovered = $discovery->discover();
$measure('discovery (scan)', static fn () => $discovery->discover());
$measure('fingerprint (stat)', static fn () => $discovered->fingerprint());

$result = $registrar->build($discovered);
$measure('build (load + validate all)', static fn () => $registrar->build($discovered));

$manifest = new Manifest($result->index, $discovered->fingerprint(), gmdate('c'));
$measure('manifest write', static fn () => $cache->write($manifest));
$measure('manifest read', static fn () => $cache->read());
$measure('manifest read (again)', static fn () => $cache->read());

$reread = $cache->read();
$index = $reread === null ? $result->index : $reread->index;
$identities = $index->identities();
$total = count($identities);

$measure(sprintf('%d identity lookups', $lookups), static function () use ($index, $identities, $total, $lookups): void {
    $found = 0;

    for ($lookup = 0; $lookup < $lookups; $lookup++) {
        $found += $index->get($identities[$lookup % $total]) === null ? 0 : 1;
    }

    if ($found !== $lookups) {
        throw new RuntimeException('Lookup mismatch.');
    }
}, $lookups);
$measure('filter by kind', static fn () => $index->ofKind('module'));

$resolver = new DefinitionResolver($loader, $kinds);
$descriptor = $index->get('module:feature-0');

if ($descriptor !== null) {
    $measure('lazy resolve one (load + validate)', static fn () => $resolver->resolve($descriptor));
}

printf("\nPeak memory: %.1f MB\n", memory_get_peak_usage(true) / 1048576);

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

foreach ($iterator as $item) {
    if ($item instanceof SplFileInfo) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
}

rmdir($root);
