<?php

namespace Wexample\SymfonyActivity\Class;

use DateTimeImmutable;
use Wexample\SymfonyActivity\Enum\ActivityCategory;

/**
 * One line of a history as it is read: an event of the activity table, or a
 * change auditor recorded — `change.insert`, `change.update`,
 * `change.remove`, `change.associate`, `change.dissociate`, in the `change`
 * category. Both answer to the getters of an Activity, so code and templates
 * written for one read the other.
 */
final readonly class ActivityEntry
{
    /**
     * @param array<string, scalar|null> $context
     * @param list<array{field: string, old: ?string, new: ?string}> $changes the fields a change touched,
     *                                                                        each value worded
     * @param ?string $actorId null when the subject did it itself; a change knows its actor by id only
     */
    public function __construct(
        private string $category,
        private string $type,
        private string $subjectType,
        private ?string $subjectId,
        private ?string $actorType,
        private ?string $actorId,
        private array $context,
        private DateTimeImmutable $occurredAt,
        private array $changes = [],
        private ?string $subjectLabel = null,
        private ?string $actorLabel = null,
    ) {
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getSubjectType(): string
    {
        return $this->subjectType;
    }

    public function getSubjectId(): ?string
    {
        return $this->subjectId;
    }

    public function getActorType(): ?string
    {
        return $this->actorType;
    }

    public function getActorId(): ?string
    {
        return $this->actorId;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * UTC.
     */
    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * @return list<array{field: string, old: ?string, new: ?string}>
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * What the entry is about, as a screen names it; null until the journal
     * labels it.
     */
    public function getSubjectLabel(): ?string
    {
        return $this->subjectLabel;
    }

    /**
     * Who did it when it was not the subject itself.
     */
    public function getActorLabel(): ?string
    {
        return $this->actorLabel;
    }

    public function isChange(): bool
    {
        return $this->category === ActivityCategory::CHANGE->value;
    }

    public function withLabels(?string $subjectLabel, ?string $actorLabel): self
    {
        return new self(
            $this->category,
            $this->type,
            $this->subjectType,
            $this->subjectId,
            $this->actorType,
            $this->actorId,
            $this->context,
            $this->occurredAt,
            $this->changes,
            $subjectLabel,
            $actorLabel,
        );
    }
}
