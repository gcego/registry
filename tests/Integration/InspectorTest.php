<?php

declare(strict_types=1);

use Gcego\Registry\Diagnostics\DiagnosticLevel;
use Gcego\Registry\Diagnostics\RegistryInspector;
use Gcego\Registry\Registry;

it('reports a healthy registry', function (): void {
    $this->workspace->module('Modules/Subjects/Subjects.php', 'subjects');
    $this->workspace->module('Modules/Topics/Topics.php', 'topics');
    $report = app(RegistryInspector::class)->inspect();

    expect($report->isHealthy())->toBeTrue()
        ->and($report->countsByKind)->toBe(['module' => 2])
        ->and($report->roots[0]['exists'])->toBeTrue()
        ->and($report->roots[1]['kindRegistered'])->toBeFalse()
        ->and($report->check('registration.count')[0]->message)->toBe('2 definition(s) registered.')
        ->and($report->cache['enabled'])->toBeFalse()
        ->and($report->toArray()['healthy'])->toBeTrue();
});

it('reports loader failures, unsupported kinds and cache problems without throwing', function (): void {
    $this->rebuildApplication(['cache.enabled' => true]);
    $this->workspace->module('Modules/Subjects/Subjects.php', 'subjects');
    $this->workspace->file('Modules/Broken/Broken.php', '<?php return 42;');
    $this->workspace->file('Pages/Dashboard.php', '<?php return null;');
    $this->workspace->file('cache/gcego-registry.php', '<?php return "corrupt";');

    $report = app(RegistryInspector::class)->inspect();

    expect($report->isHealthy())->toBeFalse()
        ->and($report->check('registration.failure'))->toHaveCount(1)
        ->and($report->check('registration.unsupported'))->toHaveCount(1)
        ->and($report->check('discovery.kind')[0]->level)->toBe(DiagnosticLevel::Warning)
        ->and($report->check('cache')[0]->level)->toBe(DiagnosticLevel::Error)
        ->and($report->countsByKind)->toBe(['module' => 1]);
});

it('reports duplicate identities and stale caches', function (): void {
    $this->rebuildApplication(['cache.enabled' => true, 'cache.verify' => false]);
    $this->workspace->module('Modules/Subjects/Subjects.php', 'subjects');
    app(Registry::class)->names();
    $this->workspace->module('Modules/Courses/Courses.php', 'subjects');

    $report = app(RegistryInspector::class)->inspect();

    expect($report->check('registration.failure')[0]->message)->toContain('is defined twice')
        ->and($report->check('cache')[0]->message)->toContain('is stale');
});
