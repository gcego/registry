<?php

declare(strict_types=1);

use Gcego\Registry\Registry;
use Gcego\Registry\Tests\Fixtures\ExampleDefinition;

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
