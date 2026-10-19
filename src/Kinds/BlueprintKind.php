<?php

declare(strict_types=1);

namespace Gcego\Registry\Kinds;

use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Blueprints\Validation\BlueprintValidationError;
use Gcego\Blueprints\Validation\BlueprintValidator;
use Gcego\Registry\Contracts\DefinitionKind;

/**
 * The built-in "blueprint" kind, backed entirely by gcego/blueprints.
 */
final readonly class BlueprintKind implements DefinitionKind
{
    public const string Id = 'blueprint';

    public function __construct(
        private BlueprintValidator $validator = new BlueprintValidator,
    ) {}

    public function id(): string
    {
        return self::Id;
    }

    public function contract(): string
    {
        return BlueprintInterface::class;
    }

    public function name(object $definition): string
    {
        return $this->blueprint($definition)->identity()->name;
    }

    public function validate(object $definition): array
    {
        return array_map(
            static fn (BlueprintValidationError $error): string => sprintf('%s: %s [%s]', $error->path, $error->message, $error->code),
            $this->validator->validate($this->blueprint($definition))->errors(),
        );
    }

    public function metadata(object $definition): array
    {
        $metadata = $this->blueprint($definition)->metadata();

        return [
            'title' => $metadata->title,
            'slug' => $metadata->slug,
            'description' => $metadata->description,
            'icon' => $metadata->icon,
            'roles' => implode(',', $this->blueprint($definition)->roles()),
        ];
    }

    private function blueprint(object $definition): BlueprintInterface
    {
        if (! $definition instanceof BlueprintInterface) {
            throw new \InvalidArgumentException(sprintf('%s is not a blueprint.', $definition::class));
        }

        return $definition;
    }
}
