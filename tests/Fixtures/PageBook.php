<?php

declare(strict_types=1);

namespace Gcego\Registry\Tests\Fixtures;

use Gcego\Registry\Contracts\DerivesDefinitions;

/**
 * A definition that brings pages with it (as a gcego/modules module does).
 */
final readonly class PageBook implements DerivesDefinitions
{
    /**
     * @param  list<string>  $pages
     */
    public function __construct(public string $name, public array $pages, public string $title = 'A page') {}

    public function derivedDefinitions(): iterable
    {
        foreach ($this->pages as $page) {
            yield new PageDefinition($this->name.'-'.$page, $this->title);
        }
    }
}
