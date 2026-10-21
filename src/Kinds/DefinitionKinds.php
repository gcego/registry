<?php

declare(strict_types=1);

namespace Gcego\Registry\Kinds;

use Gcego\Registry\Contracts\DefinitionKind;
use Gcego\Registry\Exceptions\DuplicateDefinitionException;
use Gcego\Registry\Exceptions\InvalidDefinitionException;
use Gcego\Registry\Identity\DefinitionIdentity;

/**
 * The registered definition kinds (a container singleton).
 *
 * Packages register kinds from their service provider's register() method,
 * before the registry builds its index.
 */
final class DefinitionKinds
{
    /**
     * @var array<string, DefinitionKind>
     */
    private array $kinds = [];

    /**
     * @throws InvalidDefinitionException|DuplicateDefinitionException
     */
    public function register(DefinitionKind $kind): void
    {
        $id = $kind->id();

        if (preg_match(DefinitionIdentity::KindPattern, $id) !== 1) {
            throw InvalidDefinitionException::invalidIdentity($id, 'kind identifiers must be lowercase identifiers such as "module".');
        }

        if (! interface_exists($kind->contract()) && ! class_exists($kind->contract())) {
            throw InvalidDefinitionException::invalidIdentity($id, sprintf('contract %s does not exist.', $kind->contract()));
        }

        if (isset($this->kinds[$id])) {
            if ($this->kinds[$id] === $kind || $kind::class === $this->kinds[$id]::class) {
                return;
            }

            throw DuplicateDefinitionException::forKind($id, $this->kinds[$id]::class, $kind::class);
        }

        $this->kinds[$id] = $kind;
    }

    public function has(string $id): bool
    {
        return isset($this->kinds[$id]);
    }

    public function get(string $id): ?DefinitionKind
    {
        return $this->kinds[$id] ?? null;
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_keys($this->kinds);
    }

    /**
     * Kinds whose contract the object implements, in registration order.
     *
     * When one matching contract extends another, only the most specific
     * kind matches.
     *
     * @return list<DefinitionKind>
     */
    public function matching(object $definition): array
    {
        $matching = array_values(array_filter($this->kinds, static fn (DefinitionKind $kind): bool => is_a($definition, $kind->contract())));

        return array_values(array_filter($matching, static function (DefinitionKind $kind) use ($matching): bool {
            foreach ($matching as $other) {
                if ($other->contract() !== $kind->contract() && is_a($other->contract(), $kind->contract(), true)) {
                    return false;
                }
            }

            return true;
        }));
    }
}
