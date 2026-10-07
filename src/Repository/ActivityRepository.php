<?php

namespace Wexample\SymfonyActivity\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Wexample\SymfonyActivity\Class\ActivitySubject;
use Wexample\SymfonyActivity\Entity\Activity;

/**
 * @extends ServiceEntityRepository<Activity>
 */
class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    /**
     * The history of $subject, newest first, narrowed to $category when one
     * is given: what a list pages through.
     */
    public function createSubjectQuery(ActivitySubject $subject, ?string $category = null): QueryBuilder
    {
        $builder = $this->createQueryBuilder('activity')
            ->andWhere('activity.subjectType = :type')
            ->andWhere('activity.subjectId = :id')
            ->setParameter('type', $subject->type)
            ->setParameter('id', $subject->id)
            ->orderBy('activity.occurredAt', 'DESC')
            ->addOrderBy('activity.id', 'DESC');

        if ($category !== null) {
            $builder->andWhere('activity.category = :category')->setParameter('category', $category);
        }

        return $builder;
    }

    /**
     * One page of the history of $subject, and how many pages it has: what a
     * list shows, without the page it is drawn on knowing Doctrine.
     *
     * @return array{entries: list<Activity>, count: int, pages_count: int}
     */
    public function paginate(ActivitySubject $subject, ?string $category, int $page, int $perPage): array
    {
        $paginator = new Paginator(
            $this->createSubjectQuery($subject, $category)->getQuery()
                ->setFirstResult(max(0, $page - 1) * $perPage)
                ->setMaxResults($perPage)
        );
        $count = count($paginator);

        return [
            'entries' => iterator_to_array($paginator, false),
            'count' => $count,
            'pages_count' => (int) ceil($count / $perPage),
        ];
    }

    /**
     * @return list<Activity>
     */
    public function findRecent(ActivitySubject $subject, int $limit): array
    {
        return $this->createSubjectQuery($subject)->setMaxResults($limit)->getQuery()->getResult();
    }

    /**
     * The categories $subject has entries in, for the filter of a list.
     *
     * @return list<string>
     */
    public function findSubjectCategories(ActivitySubject $subject): array
    {
        return array_column(
            $this->createQueryBuilder('activity')
                ->select('DISTINCT activity.category')
                ->andWhere('activity.subjectType = :type')
                ->andWhere('activity.subjectId = :id')
                ->setParameter('type', $subject->type)
                ->setParameter('id', $subject->id)
                ->orderBy('activity.category')
                ->getQuery()
                ->getScalarResult(),
            'category'
        );
    }
}
