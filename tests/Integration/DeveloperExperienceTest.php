<?php

declare(strict_types=1);

use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Blueprints\Contracts\BlueprintResolver;
use Gcego\Registry\Registry;

it('discovers a module just by creating its file', function (): void {
    $this->workspace->blueprint('Modules/Academics/Subjects/Subjects.php', 'subjects', "\n    new Property(Blueprint::Title, 'Subject Management'),");
    $this->workspace->file('Modules/Academics/Subjects/Table.php', '<?php throw new RuntimeException("helper files are never loaded");');
    $this->workspace->blueprint('Modules/Academics/Topics/Topics.php', 'topics');
    $this->workspace->blueprint('Modules/Assessments/PastPapers/PastPapers.php', 'past-papers');

    $registry = app(Registry::class);

    expect($registry->names('blueprint'))->toBe(['subjects', 'topics', 'past-papers'])
        ->and($registry->get('subjects'))->toBeInstanceOf(BlueprintInterface::class)
        ->and($registry->blueprint('subjects')->title())->toBe('Subject Management')
        ->and($registry->descriptor('blueprint:past-papers')->metadata['title'])->toBe('Past Papers')
        ->and(app(BlueprintResolver::class)->resolve('topics')->name())->toBe('topics')
        ->and(app(BlueprintResolver::class)->names())->toBe(['subjects', 'topics', 'past-papers']);
});
