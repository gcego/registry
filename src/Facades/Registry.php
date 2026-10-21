<?php

declare(strict_types=1);

namespace Gcego\Registry\Facades;

use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Registry\Index\DefinitionIndex;
use Gcego\Registry\Index\SourceType;
use Gcego\Registry\Registration\RegistrationFailure;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool has(string $identifier)
 * @method static DefinitionDescriptor descriptor(string $identifier)
 * @method static object get(string $identifier, ?string $expected = null)
 * @method static list<DefinitionDescriptor> descriptors(?string $kind = null, ?string $scope = null, ?SourceType $sourceType = null, ?string $location = null)
 * @method static list<string> names(?string $kind = null)
 * @method static int count(?string $kind = null)
 * @method static list<string> kinds()
 * @method static DefinitionIndex index()
 * @method static list<RegistrationFailure> failures()
 * @method static string origin()
 * @method static DefinitionDescriptor register(object $definition, ?string $scope = null)
 * @method static void refresh()
 *
 * @see \Gcego\Registry\Registry
 */
final class Registry extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Gcego\Registry\Registry::class;
    }
}
