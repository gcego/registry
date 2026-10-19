<?php

declare(strict_types=1);

namespace Gcego\Registry\Identity;

use Gcego\Registry\Exceptions\InvalidDefinitionException;
use Stringable;

/**
 * The logical identity of a registered definition: kind, optional scope, name.
 *
 * String form: "kind:name" or "kind:scope.name", for example
 * "blueprint:subjects" or "blueprint:admin.subjects". Every part is
 * lowercase, so identities compare exactly. Identity never depends on file
 * paths: the name comes from the definition itself.
 */
final readonly class DefinitionIdentity implements Stringable
{
    public const string KindPattern = '/^[a-z][a-z0-9]*(?:[-_][a-z0-9]+)*$/';

    public const string NamePattern = '/^[a-z][a-z0-9]*(?:[-_][a-z0-9]+)*$/';

    public const string ScopePattern = '/^[a-z][a-z0-9]*(?:[-_][a-z0-9]+)*(?:\.[a-z][a-z0-9]*(?:[-_][a-z0-9]+)*)*$/';

    public function __construct(
        public string $kind,
        public string $name,
        public ?string $scope = null,
    ) {
        if (preg_match(self::KindPattern, $kind) !== 1) {
            throw InvalidDefinitionException::invalidIdentity($this->toString(), sprintf('kind "%s" must be a lowercase identifier such as "blueprint".', $kind));
        }

        if (preg_match(self::NamePattern, $name) !== 1 || strlen($name) > 100) {
            throw InvalidDefinitionException::invalidIdentity($this->toString(), sprintf('name "%s" must be a lowercase identifier such as "subjects".', $name));
        }

        if ($scope !== null && preg_match(self::ScopePattern, $scope) !== 1) {
            throw InvalidDefinitionException::invalidIdentity($this->toString(), sprintf('scope "%s" must be lowercase dotted identifiers such as "admin" or "school.staff".', $scope));
        }
    }

    /**
     * Parse "kind:name" or "kind:scope.name".
     *
     * @throws InvalidDefinitionException
     */
    public static function fromString(string $identity): self
    {
        $separator = strpos($identity, ':');

        if ($separator === false) {
            throw InvalidDefinitionException::invalidIdentity($identity, 'expected "kind:name" or "kind:scope.name".');
        }

        $kind = substr($identity, 0, $separator);
        $rest = substr($identity, $separator + 1);
        $dot = strrpos($rest, '.');

        return $dot === false
            ? new self($kind, $rest)
            : new self($kind, substr($rest, $dot + 1), substr($rest, 0, $dot));
    }

    public static function isQualified(string $identity): bool
    {
        return str_contains($identity, ':');
    }

    public function equals(self $other): bool
    {
        return $this->toString() === $other->toString();
    }

    public function toString(): string
    {
        return $this->kind.':'.($this->scope === null ? '' : $this->scope.'.').$this->name;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
