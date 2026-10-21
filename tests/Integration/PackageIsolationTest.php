<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('discovers caches and resolves definitions with only core available', function (): void {
    $this->workspace->file('Pages/home.php', '<?php return new Gcego\\Registry\\Tests\\Fixtures\\PageDefinition("home", "Home");');

    $script = <<<'CODE'
require $argv[1].'/vendor/autoload.php';
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Gcego\\') && ! str_starts_with($class, 'Gcego\\Registry\\') && ! str_starts_with($class, 'Gcego\\Core\\')) {
        throw new RuntimeException('Unexpected package dependency: '.$class);
    }
}, true, true);
$app = new Illuminate\Foundation\Application($argv[1]);
$app->instance('config', new Illuminate\Config\Repository);
$app->register(Gcego\Registry\RegistryServiceProvider::class);
$app['config']->set('gcego-registry.discovery.roots', [
    ['path' => $argv[2].'/Pages', 'kind' => 'page', 'entrypoint' => 'file'],
]);
$app['config']->set('gcego-registry.cache.path', $argv[2].'/cache.php');
$app['config']->set('gcego-registry.cache.verify', false);
$app->make(Gcego\Registry\Kinds\DefinitionKinds::class)->register(new Gcego\Registry\Tests\Fixtures\PageKind);
$registry = $app->make(Gcego\Registry\Registry::class);
if ($registry->get('page:home')->title !== 'Home') {
    throw new RuntimeException('Discovery failed');
}
$registry->refresh();
if ($registry->get('page:home')->title !== 'Home' || $registry->origin() !== 'cache') {
    throw new RuntimeException('Cached resolution failed');
}
$definition = new Gcego\Registry\Tests\Fixtures\PageDefinition('settings', 'Settings');
$registry->register($definition);
if ($registry->get('page:settings') !== $definition || $registry->count() !== 2) {
    throw new RuntimeException('Runtime registration failed');
}
echo 'ok';
CODE;

    $process = new Process([PHP_BINARY, '-r', $script, dirname(__DIR__, 2), $this->workspace->root]);
    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and($process->getOutput())->toBe('ok');
});
