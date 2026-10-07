<?php

namespace Wexample\SymfonyActivity\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Wexample\SymfonyActivity\Class\ActivityEntry;
use Wexample\SymfonyActivity\Class\ActivityJournalFilter;
use Wexample\SymfonyActivity\Class\ActivitySubject;
use Wexample\SymfonyActivity\Entity\Activity;
use Wexample\SymfonyActivity\Service\ActivityJournalService;

/**
 * The history of one subject, newest first: its events and the changes made
 * to it, as ActivityJournalService reads them. What reads anything else than
 * one subject — what someone did, a category over a period — asks the
 * journal itself.
 *
 * @extends ServiceEntityRepository<Activity>
 */
class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ActivityJournalService $journal,
    ) {
        parent::__construct($registry, Activity::class);
    }

    /**
     * One page of the history of $subject, and how many pages it has: what a
     * list shows, without the page it is drawn on knowing Doctrine.
     *
     * @return array{entries: list<ActivityEntry>, count: int, pages_count: int}
     */
    public function paginate(ActivitySubject $subject, ?string $category, int $page, int $perPage): array
    {
        return $this->journal->paginate(new ActivityJournalFilter(subject: $subject, category: $category), $page, $perPage);
    }

    /**
     * @return list<ActivityEntry>
     */
    public function findRecent(ActivitySubject $subject, int $limit): array
    {
        return $this->journal->findRecent(new ActivityJournalFilter(subject: $subject), $limit);
    }

    /**
     * The categories $subject has entries in, for the filter of a list.
     *
     * @return list<string>
     */
    public function findSubjectCategories(ActivitySubject $subject): array
    {
        return $this->journal->findCategories(new ActivityJournalFilter(subject: $subject));
    }
}
