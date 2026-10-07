<?php

namespace Wexample\SymfonyActivity\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\SymfonyActivity\Class\ActivityEntry;
use Wexample\SymfonyActivity\Class\ActivityJournalFilter;
use Wexample\SymfonyActivity\Class\ActivitySubject;
use Wexample\SymfonyActivity\Entity\Activity;

/**
 * What a person may ask of their history, where the law of the application's
 * market gives them the right to: a copy of it, its erasure. Nothing calls it
 * by itself — the application decides whether, and from where.
 *
 * Their history is what happened to them and what they did elsewhere: the
 * events of others they are the actor of, the fields they changed. A field
 * change knows its author by the id of a signed-in user, so only a subject
 * that signs in — a security user — has made any.
 */
class ActivitySubjectDataService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityJournalService $journal,
        private readonly ActivityChangeTablesService $changeTables,
        private readonly ActivitySubjectService $subjects,
    ) {
    }

    /**
     * Every entry of $subject, oldest first, as plain arrays: its own history
     * — `by_someone_else` when another did it —, then mixed in by time what it
     * did in the history of others — `about_someone_else`, naming only the
     * type of what it was about.
     *
     * @return list<array{category: string, type: string, occurred_at: string, context: array<string, scalar|null>, changes: list<array{field: string, old: ?string, new: ?string}>, by_someone_else: bool, about_someone_else: ?string}>
     */
    public function export(object $subject): array
    {
        $subject = $this->subjects->of($subject);

        $entries = [
            ...$this->journal->findAll(new ActivityJournalFilter(subject: $subject)),
            ...array_filter(
                $this->journal->findAll(new ActivityJournalFilter(actor: $subject)),
                // Its own history is read above.
                static fn (ActivityEntry $entry) => ! self::isAbout($entry, $subject)
            ),
        ];

        usort($entries, static fn (ActivityEntry $a, ActivityEntry $b) => $a->getOccurredAt() <=> $b->getOccurredAt());

        return array_map(static fn (ActivityEntry $entry) => [
            'category' => $entry->getCategory(),
            'type' => $entry->getType(),
            'occurred_at' => $entry->getOccurredAt()->format(DATE_ATOM),
            'context' => $entry->getContext(),
            'changes' => $entry->getChanges(),
            'by_someone_else' => self::isAbout($entry, $subject) && ! self::isBy($entry, $subject),
            'about_someone_else' => self::isAbout($entry, $subject) ? null : $entry->getSubjectType(),
        ], $entries);
    }

    /**
     * Removes the history of $subject — its events and the changes made to
     * it —, and takes its name off what it did elsewhere: the events and the
     * changes stay theirs, by nobody.
     *
     * @return int the entries removed
     */
    public function erase(object $subject): int
    {
        $subject = $this->subjects->of($subject);
        $connection = $this->entityManager->getConnection();

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

        if (is_a($subject->type, UserInterface::class, true)) {
            foreach (array_keys($this->changeTables->getTables()) as $table) {
                $connection->executeStatement(
                    'UPDATE '.$table.' SET blame_id = NULL, blame_user = NULL, blame_user_fqdn = NULL, blame_user_firewall = NULL, ip = NULL WHERE blame_id = :id',
                    ['id' => $subject->id]
                );
            }
        }

        $removed = $this->entityManager->createQueryBuilder()
            ->delete(Activity::class, 'activity')
            ->andWhere('activity.subjectType = :type')
            ->andWhere('activity.subjectId = :id')
            ->setParameter('type', $subject->type)
            ->setParameter('id', $subject->id)
            ->getQuery()
            ->execute();

        if ($table = $this->changeTables->getTable($subject->type)) {
            $removed += $connection->executeStatement(
                'DELETE FROM '.$table.' WHERE object_id = :id AND (discriminator IS NULL OR discriminator = :type)',
                ['id' => $subject->id, 'type' => $subject->type]
            );
        }

        return $removed;
    }

    private static function isAbout(ActivityEntry $entry, ActivitySubject $subject): bool
    {
        return $entry->getSubjectType() === $subject->type && $entry->getSubjectId() === $subject->id;
    }

    /**
     * Whether $subject did it: an event naming no other actor, a change it
     * is the author of.
     */
    private static function isBy(ActivityEntry $entry, ActivitySubject $subject): bool
    {
        return $entry->isChange()
            ? $entry->getActorId() === $subject->id
            : $entry->getActorId() === null || ($entry->getActorType() === $subject->type && $entry->getActorId() === $subject->id);
    }
}
