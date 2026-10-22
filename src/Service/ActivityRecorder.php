<?php

namespace Wexample\SymfonyActivity\Service;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Wexample\SymfonyActivity\Class\ActivitySubject;
use Wexample\SymfonyActivity\Entity\Activity;
use Wexample\SymfonyActivity\Enum\ActivityCategory;

/**
 * Writes what happened to a subject, when its category is enabled — and
 * nothing at all otherwise, so a call can stay in the code of a package
 * whatever the application decided to keep.
 *
 * The row is inserted straight through the connection, not persisted: a
 * flush would carry along whatever the caller has pending, and an entry is
 * often written from inside someone else's unit of work — a security event
 * dispatched mid-request, a listener of a flush.
 */
class ActivityRecorder
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityPolicyService $policy,
        private readonly ActivitySubjectService $subjects,
    ) {
    }

    /**
     * @param object $subject whose history it is: an entity, an ActivitySubjectInterface, an ActivitySubject
     * @param string $type a stable code, `support.contacted`
     * @param array<string, scalar|null> $context what the entry needs to be read later; no secret, and as
     *                                            little about people as the application can do with
     * @param object|null $actor who did it, when not the subject itself
     * @param string|null $fingerprint what makes a repeat the same entry, in a category merging
     *                                 its repeats (`merge_within`); a hash, 64 characters at most
     */
    public function record(
        object $subject,
        string $category,
        string $type,
        array $context = [],
        ?object $actor = null,
        ?string $fingerprint = null,
    ): void {
        if ($category === ActivityCategory::CHANGE->value) {
            throw new LogicException('The `change` category is auditor\'s: mark the entity #[Auditable] rather than recording its changes.');
        }

        if (! $this->policy->isEnabled($category)) {
            return;
        }

        $subject = $this->subjects->of($subject);
        $actor = $actor ? $this->subjects->of($actor) : null;
        $mergeWithin = $this->policy->getMergeWithin($category);

        if ($fingerprint !== null && $mergeWithin !== null && $this->merge($subject, $category, $fingerprint, $mergeWithin)) {
            return;
        }

        $this->insert($subject, $category, $type, $context, $actor, $fingerprint);
    }

    /**
     * Counts a repeat on the entry of the same subject and fingerprint whose
     * last occurrence is within $within. Two requests repeating at the same
     * instant may both find none and write two entries: a count off by one,
     * never an occurrence lost.
     *
     * @return bool whether an entry took the repeat
     */
    private function merge(
        ActivitySubject $subject,
        string $category,
        string $fingerprint,
        string $within,
    ): bool {
        $metadata = $this->entityManager->getClassMetadata(Activity::class);
        $column = $metadata->getColumnName(...);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $this->entityManager->getConnection()->executeStatement(
            'UPDATE '.$metadata->getTableName()
            .' SET '.$column('occurrences').' = '.$column('occurrences').' + 1, '.$column('lastOccurredAt').' = :now'
            .' WHERE '.$column('category').' = :category'
            .' AND '.$column('fingerprint').' = :fingerprint'
            .' AND '.$column('subjectType').' = :subject_type'
            .' AND '.$column('subjectId').' = :subject_id'
            .' AND COALESCE('.$column('lastOccurredAt').', '.$column('occurredAt').') >= :since',
            [
                'now' => $now,
                'category' => $category,
                'fingerprint' => $fingerprint,
                'subject_type' => $subject->type,
                'subject_id' => $subject->id,
                'since' => $now->modify('-'.$within),
            ],
            [
                'now' => Types::DATETIME_IMMUTABLE,
                'since' => Types::DATETIME_IMMUTABLE,
            ]
        ) > 0;
    }

    /**
     * Whether anything of $category is kept: what a caller asks before
     * gathering an expensive context.
     */
    public function records(string $category): bool
    {
        return $this->policy->isEnabled($category);
    }

    /**
     * @param array<string, scalar|null> $context
     */
    private function insert(
        ActivitySubject $subject,
        string $category,
        string $type,
        array $context,
        ?ActivitySubject $actor,
        ?string $fingerprint,
    ): void {
        $metadata = $this->entityManager->getClassMetadata(Activity::class);
        $column = $metadata->getColumnName(...);

        $this->entityManager->getConnection()->insert(
            $metadata->getTableName(),
            [
                $column('id') => Uuid::v7(),
                $column('subjectType') => $subject->type,
                $column('subjectId') => $subject->id,
                $column('actorType') => $actor?->type,
                $column('actorId') => $actor?->id,
                $column('category') => $category,
                $column('type') => $type,
                $column('context') => $context,
                $column('occurredAt') => new DateTimeImmutable('now', new DateTimeZone('UTC')),
                $column('fingerprint') => $fingerprint,
                $column('occurrences') => 1,
            ],
            [
                $column('id') => UuidType::NAME,
                $column('context') => Types::JSON,
                $column('occurredAt') => Types::DATETIME_IMMUTABLE,
            ]
        );
    }
}
