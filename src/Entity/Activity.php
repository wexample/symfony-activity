<?php

namespace Wexample\SymfonyActivity\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyActivity\Repository\ActivityRepository;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;

/**
 * One thing that happened to a subject: a sign-in, a page read, a mail
 * received, a ticket opened. Written by ActivityRecorder, never changed
 * afterwards but by the purge.
 *
 * The subject and the actor are kept by type and id, not by relation: any
 * entity can have a history, and the history outlives what it is about —
 * until the retention of its category says otherwise.
 */
#[ORM\Entity(repositoryClass: ActivityRepository::class)]
#[ORM\Table(name: 'activity')]
#[ORM\Index(name: 'activity_subject', columns: ['subject_type', 'subject_id', 'occurred_at'])]
#[ORM\Index(name: 'activity_category', columns: ['category', 'occurred_at'])]
#[ORM\Index(name: 'activity_fingerprint', columns: ['category', 'fingerprint'])]
class Activity extends AbstractEntity
{
    public const string TABLE = 'activity';

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $subjectType;

    /**
     * Null once anonymized.
     */
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $subjectId = null;

    /**
     * Who did it, when it was not the subject itself: an administrator, a
     * support agent, a program.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $actorType = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $actorId = null;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $category;

    /**
     * A stable code, `security.login.succeeded`, `support.contacted`: what a
     * list filters on and a translation reads.
     */
    #[ORM\Column(type: Types::STRING, length: 128)]
    private string $type;

    /**
     * @var array<string, scalar|null>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $context = [];

    /**
     * UTC.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $occurredAt;

    /**
     * What makes two entries the same, for a category merging its repeats
     * (`merge_within`): an error's class and line, a refused limit's name.
     */
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $fingerprint = null;

    /**
     * How many times it happened: a repeat within `merge_within` counts here
     * instead of making a new entry.
     */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $occurrences = 1;

    /**
     * UTC; the last repeat counted, null when there was none.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastOccurredAt = null;

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

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getFingerprint(): ?string
    {
        return $this->fingerprint;
    }

    public function getOccurrences(): int
    {
        return $this->occurrences;
    }

    public function getLastOccurredAt(): ?DateTimeImmutable
    {
        return $this->lastOccurredAt;
    }
}
