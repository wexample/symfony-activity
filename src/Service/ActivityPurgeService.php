<?php

namespace Wexample\SymfonyActivity\Service;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Wexample\SymfonyActivity\Entity\Activity;
use Wexample\SymfonyActivity\Enum\ActivityExpiry;

/**
 * Applies the retention of each category: past it, an entry is deleted, or
 * anonymized — subject, actor and context emptied, the fact itself left to
 * be counted. Categories with no retention keep everything.
 */
class ActivityPurgeService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityPolicyService $policy,
    ) {
    }

    /**
     * @return array<string, int> the entries purged, by category
     */
    public function purge(?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $purged = [];

        foreach ($this->policy->getExpiringCategories() as $category) {
            $before = $now->modify(sprintf('-%d days', $this->policy->getRetentionDays($category)));

            $builder = $this->entityManager->createQueryBuilder();
            $builder = match ($this->policy->getExpiry($category)) {
                ActivityExpiry::DELETE => $builder->delete(Activity::class, 'activity'),
                ActivityExpiry::ANONYMIZE => $builder->update(Activity::class, 'activity')
                    ->set('activity.subjectId', 'NULL')
                    ->set('activity.actorType', 'NULL')
                    ->set('activity.actorId', 'NULL')
                    ->set('activity.context', ':empty')
                    ->setParameter('empty', '[]')
                    // Anonymized already: nothing left to take away.
                    ->andWhere('activity.subjectId IS NOT NULL'),
            };

            $purged[$category] = $builder
                ->andWhere('activity.category = :category')
                ->andWhere('activity.occurredAt < :before')
                ->setParameter('category', $category)
                ->setParameter('before', $before)
                ->getQuery()
                ->execute();
        }

        return $purged;
    }
}
