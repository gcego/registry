<?php

declare(strict_types=1);

use Gcego\Registry\Registry;
use Gcego\Registry\Tests\Fixtures\ExampleDefinition;
use Gcego\Registry\Tests\Fixtures\LoadCounter;

it('discovers a module just by creating its file', function (): void {
    $this->workspace->module('Modules/Academics/Subjects/Subjects.php', 'subjects', "'Subject Management'");
    $this->workspace->file('Modules/Academics/Subjects/Table.php', '<?php throw new RuntimeException("helper files are never loaded");');
    $this->workspace->module('Modules/Academics/Topics/Topics.php', 'topics');
    $this->workspace->module('Modules/Assessments/PastPapers/PastPapers.php', 'past-papers');

    $registry = app(Registry::class);

    expect($registry->names('module'))->toBe(['subjects', 'topics', 'past-papers'])
        ->and($registry->get('subjects'))->toBeInstanceOf(ExampleDefinition::class)
        ->and($registry->get('module:'.'subjects')->title())->toBe('Subject Management')
        ->and($registry->descriptor('module:past-papers')->metadata['title'])->toBe('Past Papers');
});

it('registers the nested Subjects blueprint and verifies changes to its supporting files', function (): void {
    $base = 'Modules/Academics/Subjects/';
    $this->workspace->module($base.'Subjects.php', 'subjects', "'Subject Management'");

    foreach ([
        'Pages/ListSubjects.php', 'Pages/CreateSubject.php', 'Pages/EditSubject.php', 'Pages/ViewSubject.php',
        'Tables/SubjectsTable.php', 'Tables/Columns/SubjectName.php', 'Tables/Filters/ActiveSubjects.php',
        'Forms/SubjectForm.php', 'Forms/Fields/SubjectName.php', 'Forms/Sections/SubjectDetails.php',
        'Infolists/SubjectInfolist.php', 'Infolists/Entries/SubjectName.php',
        'Actions/CreateSubject.php', 'Actions/DeleteSubject.php', 'Actions/ExportSubjects.php',
        'Relations/TopicsRelation.php', 'Relations/SyllabiRelation.php',
        'Widgets/SubjectsOverview.php', 'Widgets/SubjectsStatistics.php',
    ] as $file) {
        $this->workspace->file($base.$file, '<?php throw new RuntimeException("Supporting files must not be loaded as entrypoints");');
    }

    $config = ['cache.enabled' => true, 'cache.verify' => true];
    $this->rebuildApplication($config);

    expect(app(Registry::class)->names())->toBe(['subjects'])
        ->and(app(Registry::class)->get('module:subjects')->title())->toBe('Subject Management');

    $this->rebuildApplication($config);
    LoadCounter::reset();

    expect(app(Registry::class)->names())->toBe(['subjects'])
        ->and(app(Registry::class)->origin())->toBe('cache')
        ->and(LoadCounter::total())->toBe(0);

    $this->workspace->file($base.'Forms/SubjectForm.php', '<?php throw new RuntimeException("Updated supporting form");');
    $this->rebuildApplication($config);

    expect(app(Registry::class)->names())->toBe(['subjects'])
        ->and(app(Registry::class)->origin())->toBe('discovery');
});

it('refreshes nested module metadata only after an explicit production cache rebuild', function (): void {
    $base = 'Modules/Academics/Subjects/';
    $this->workspace->module($base.'Subjects.php', 'subjects', "require __DIR__.'/Forms/SubjectForm.php'");
    $this->workspace->file($base.'Forms/SubjectForm.php', "<?php return 'Original subject form';");
    $config = ['cache.enabled' => true, 'cache.verify' => false, 'cache.missing' => 'fail'];
    $this->rebuildApplication($config);

    // Build on deploy before any request; requests may not create a missing cache.
    $this->artisan('gcego:registry-cache')->assertSuccessful();
    $this->rebuildApplication($config);
    LoadCounter::reset();

    expect(app(Registry::class)->descriptor('module:subjects')->metadata['title'])->toBe('Original subject form')
        ->and(app(Registry::class)->origin())->toBe('cache')
        ->and(LoadCounter::total())->toBe(0);

    // Only a supporting file changes; the blueprint entrypoint stays identical.
    $this->workspace->file($base.'Forms/SubjectForm.php', "<?php return 'Updated subject form for production';");
    $this->rebuildApplication($config);

    expect(app(Registry::class)->descriptor('module:subjects')->metadata['title'])->toBe('Original subject form')
        ->and(app(Registry::class)->origin())->toBe('cache')
        ->and(LoadCounter::total())->toBe(0);

    $this->artisan('gcego:registry-cache')->assertSuccessful();
    expect(LoadCounter::total())->toBe(1);

    // A fresh request reads the rebuilt manifest without executing the blueprint.
    $this->rebuildApplication($config);
    LoadCounter::reset();

    expect(app(Registry::class)->descriptor('module:subjects')->metadata['title'])->toBe('Updated subject form for production')
        ->and(app(Registry::class)->origin())->toBe('cache')
        ->and(LoadCounter::total())->toBe(0);
});
