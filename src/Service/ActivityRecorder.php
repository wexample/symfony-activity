<?php

namespace Wexample\SymfonyActivity\Service;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Wexample\SymfonyActivity\Class\ActivitySubject;
use Wexample\SymfonyActivity\Entity\Activity;

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
     */
    public function record(
        object $subject,
        string $category,
        string $type,
        array $context = [],
        ?object $actor = null,
    ): void {
        if (! $this->policy->isEnabled($category)) {
            return;
        }

        $subject = $this->subjects->of($subject);
        $actor = $actor ? $this->subjects->of($actor) : null;

        $this->insert($subject, $category, $type, $context, $actor);
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
            ],
            [
                $column('id') => UuidType::NAME,
                $column('context') => Types::JSON,
                $column('occurredAt') => Types::DATETIME_IMMUTABLE,
            ]
        );
    }
}
