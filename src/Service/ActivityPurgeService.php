<?php

namespace Wexample\SymfonyActivity\Service;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Wexample\SymfonyActivity\Entity\Activity;
use Wexample\SymfonyActivity\Enum\ActivityCategory;
use Wexample\SymfonyActivity\Enum\ActivityExpiry;

/**
 * Applies the retention of each category: past it, an entry is deleted, or
 * anonymized — subject, actor and context emptied, the fact itself left to
 * be counted. Categories with no retention keep everything.
 *
 * The `change` category is auditor's tables: a change past its retention is
 * deleted, or anonymized — its author and address emptied, the change itself
 * kept, since it is what the entity went through.
 */
class ActivityPurgeService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityPolicyService $policy,
        private readonly ActivityChangeTablesService $changeTables,
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

            $purged[$category] = $category === ActivityCategory::CHANGE->value
                ? $this->purgeChanges($this->policy->getExpiry($category), $before)
                : $this->purgeEvents($category, $this->policy->getExpiry($category), $before);
        }

        return $purged;
    }

    private function purgeEvents(string $category, ActivityExpiry $expiry, DateTimeImmutable $before): int
    {
        $builder = $this->entityManager->createQueryBuilder();
        $builder = match ($expiry) {
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

        return $builder
            ->andWhere('activity.category = :category')
            ->andWhere('activity.occurredAt < :before')
            ->setParameter('category', $category)
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }

    private function purgeChanges(ActivityExpiry $expiry, DateTimeImmutable $before): int
    {
        $connection = $this->entityManager->getConnection();
        $purged = 0;

        foreach (array_keys($this->changeTables->getTables()) as $table) {
            $purged += $connection->executeStatement(
                match ($expiry) {
                    ActivityExpiry::DELETE => 'DELETE FROM '.$table.' WHERE created_at < :before',
                    ActivityExpiry::ANONYMIZE => 'UPDATE '.$table.' SET blame_id = NULL, blame_user = NULL, blame_user_fqdn = NULL, blame_user_firewall = NULL, ip = NULL'
                        .' WHERE created_at < :before AND (blame_id IS NOT NULL OR blame_user IS NOT NULL OR ip IS NOT NULL)',
                },
                ['before' => $before],
                ['before' => Types::DATETIME_IMMUTABLE]
            );
        }

        return $purged;
    }
}
