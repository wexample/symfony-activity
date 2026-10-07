<?php

namespace Wexample\SymfonyActivity\Service;

use Doctrine\ORM\EntityManagerInterface;
use Wexample\SymfonyActivity\Entity\Activity;
use Wexample\SymfonyActivity\Repository\ActivityRepository;

/**
 * What a person may ask of their history, where the law of the application's
 * market gives them the right to: a copy of it, its erasure. Nothing calls it
 * by itself — the application decides whether, and from where.
 */
class ActivitySubjectDataService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityRepository $repository,
        private readonly ActivitySubjectService $subjects,
    ) {
    }

    /**
     * Every entry of $subject, oldest first, as plain arrays.
     *
     * @return list<array{category: string, type: string, occurred_at: string, context: array<string, scalar|null>, by_someone_else: bool}>
     */
    public function export(object $subject): array
    {
        $entries = $this->repository->createSubjectQuery($this->subjects->of($subject))
            ->orderBy('activity.occurredAt', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(static fn (Activity $activity) => [
            'category' => $activity->getCategory(),
            'type' => $activity->getType(),
            'occurred_at' => $activity->getOccurredAt()->format(DATE_ATOM),
            'context' => $activity->getContext(),
            'by_someone_else' => $activity->getActorId() !== null,
        ], $entries);
    }

    /**
     * Removes the history of $subject, and takes its name off the entries it
     * wrote about others — those stay theirs.
     *
     * @return int the entries removed
     */
    public function erase(object $subject): int
    {
        $subject = $this->subjects->of($subject);

        $this->entityManager->createQueryBuilder()
            ->update(Activity::class, 'activity')
            ->set('activity.actorType', 'NULL')
            ->set('activity.actorId', 'NULL')
            ->andWhere('activity.actorType = :type')
            ->andWhere('activity.actorId = :id')
            ->setParameter('type', $subject->type)
            ->setParameter('id', $subject->id)
            ->getQuery()
            ->execute();

        return $this->entityManager->createQueryBuilder()
            ->delete(Activity::class, 'activity')
            ->andWhere('activity.subjectType = :type')
            ->andWhere('activity.subjectId = :id')
            ->setParameter('type', $subject->type)
            ->setParameter('id', $subject->id)
            ->getQuery()
            ->execute();
    }
}
