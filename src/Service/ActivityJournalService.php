<?php

namespace Wexample\SymfonyActivity\Service;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\SymfonyActivity\Class\ActivityEntry;
use Wexample\SymfonyActivity\Class\ActivityJournalFilter;
use Wexample\SymfonyActivity\Entity\Activity;
use Wexample\SymfonyActivity\Enum\ActivityCategory;
use Wexample\SymfonyActivity\Helper\ActivityChangeHelper;

/**
 * Everything logged about the application, read as one journal: the events
 * of the activity table and the field changes auditor keeps in its own
 * tables, merged by time, newest first. The history of a subject, what
 * someone did, a category over a period — each is a filter of it.
 *
 * One SQL union over the activity table and each audit table, so that a page
 * of it is a page of both, counted and ordered by the database — written for
 * PostgreSQL, and SQLite for the tests.
 */
class ActivityJournalService
{
    private const string SOURCE_ACTIVITY = 'activity';

    private const string SOURCE_CHANGE = 'change';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityChangeTablesService $changeTables,
        private readonly ActivitySubjectService $subjects,
    ) {
    }

    /**
     * One page of what $filter narrows to, labelled for a screen, and how
     * many pages there are.
     *
     * @return array{entries: list<ActivityEntry>, count: int, pages_count: int}
     */
    public function paginate(ActivityJournalFilter $filter, int $page, int $perPage): array
    {
        $count = $this->count($filter);

        return [
            'entries' => $this->label($this->read($filter, 'DESC', $perPage, max(0, $page - 1) * $perPage)),
            'count' => $count,
            'pages_count' => (int) ceil($count / $perPage),
        ];
    }

    /**
     * @return list<ActivityEntry>
     */
    public function findRecent(ActivityJournalFilter $filter, int $limit): array
    {
        return $this->label($this->read($filter, 'DESC', $limit));
    }

    /**
     * Every entry $filter narrows to, oldest first and unlabelled: what a
     * copy of it is made from.
     *
     * @return list<ActivityEntry>
     */
    public function findAll(ActivityJournalFilter $filter): array
    {
        return $this->read($filter, 'ASC');
    }

    public function count(ActivityJournalFilter $filter): int
    {
        $params = [];
        $types = [];

        if (! $union = $this->union($filter, $params, $types)) {
            return 0;
        }

        return (int) $this->entityManager->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM ('.$union.') journal', $params, $types);
    }

    /**
     * The categories $filter has entries in, for the filter of a list.
     *
     * @return list<string>
     */
    public function findCategories(ActivityJournalFilter $filter): array
    {
        return $this->distinct('category', $filter);
    }

    /**
     * The types $filter has entries of, for the filter of a list.
     *
     * @return list<string>
     */
    public function findTypes(ActivityJournalFilter $filter): array
    {
        return $this->distinct('type', $filter);
    }

    /**
     * @return list<string>
     */
    private function distinct(string $column, ActivityJournalFilter $filter): array
    {
        $params = [];
        $types = [];

        if (! $union = $this->union($filter, $params, $types)) {
            return [];
        }

        return $this->entityManager->getConnection()->fetchFirstColumn(
            'SELECT DISTINCT '.$column.' FROM ('.$union.') journal ORDER BY '.$column,
            $params,
            $types
        );
    }

    /**
     * @return list<ActivityEntry>
     */
    private function read(ActivityJournalFilter $filter, string $direction, ?int $limit = null, int $offset = 0): array
    {
        $params = [];
        $types = [];

        if (! $union = $this->union($filter, $params, $types)) {
            return [];
        }

        $connection = $this->entityManager->getConnection();
        // Entries of one second keep an order of their own: a page never
        // repeats nor skips one.
        $sql = 'SELECT * FROM ('.$union.') journal'
            .' ORDER BY occurred_at '.$direction.', source '.$direction.', sort_number '.$direction.', sort_text '.$direction;

        if ($limit !== null) {
            $sql = $connection->getDatabasePlatform()->modifyLimitQuery($sql, $limit, $offset);
        }

        return array_map(
            $this->hydrate(...),
            $connection->fetchAllAssociative($sql, $params, $types)
        );
    }

    /**
     * The activity table and each audit table, as rows of one shape, each
     * narrowed by $filter; null when nothing can match.
     *
     * @param array<string, mixed> $params filled with what the union binds
     * @param array<string, string> $types
     */
    private function union(ActivityJournalFilter $filter, array &$params, array &$types): ?string
    {
        $parts = [];
        $change = ActivityCategory::CHANGE->value;

        if ($filter->category !== $change && ! str_starts_with((string) $filter->type, $change.'.')) {
            $parts[] = $this->activityPart($filter, $params, $types);
        }

        if (($filter->category === null || $filter->category === $change)
            && ($filter->type === null || str_starts_with($filter->type, $change.'.'))
            // A change knows who made it by the id of a signed-in user only.
            && ($filter->actor === null || is_a($filter->actor->type, UserInterface::class, true))) {
            $index = 0;

            foreach ($this->changeTables->getTables() as $table => $class) {
                if ($filter->subject && $this->changeTables->getTable($filter->subject->type) !== $table) {
                    continue;
                }

                $parts[] = $this->changePart($table, $class, 'c'.$index++, $filter, $params, $types);
            }
        }

        return $parts ? implode(' UNION ALL ', $parts) : null;
    }

    private function activityPart(ActivityJournalFilter $filter, array &$params, array &$types): string
    {
        $metadata = $this->entityManager->getClassMetadata(Activity::class);
        $column = static fn (string $field) => 'a.'.$metadata->getColumnName($field);
        $where = [];

        if ($filter->subject) {
            $where[] = $column('subjectType').' = :a_subject_type AND '.$column('subjectId').' = :a_subject_id';
            $params['a_subject_type'] = $filter->subject->type;
            $params['a_subject_id'] = $filter->subject->id;
        }

        if ($filter->category !== null) {
            $where[] = $column('category').' = :a_category';
            $params['a_category'] = $filter->category;
        }

        if ($filter->type !== null) {
            $where[] = $column('type').' = :a_type';
            $params['a_type'] = $filter->type;
        }

        if ($filter->actor) {
            $where[] = '(('.$column('actorType').' = :a_actor_type AND '.$column('actorId').' = :a_actor_id)'
                .' OR ('.$column('actorId').' IS NULL AND '.$column('subjectType').' = :a_actor_type AND '.$column('subjectId').' = :a_actor_id))';
            $params['a_actor_type'] = $filter->actor->type;
            $params['a_actor_id'] = $filter->actor->id;
        }

        $this->wherePeriod($where, $column('occurredAt'), 'a', $filter, $params, $types);

        return 'SELECT '.implode(', ', [
            "'".self::SOURCE_ACTIVITY."' AS source",
            'CAST(NULL AS BIGINT) AS sort_number',
            $this->sortableUuid($column('id')).' AS sort_text',
            $column('category').' AS category',
            $column('type').' AS type',
            $column('subjectType').' AS subject_type',
            $column('subjectId').' AS subject_id',
            $column('actorType').' AS actor_type',
            $column('actorId').' AS actor_id',
            'CAST(NULL AS TEXT) AS actor_name',
            'CAST('.$column('context').' AS TEXT) AS payload',
            $column('occurredAt').' AS occurred_at',
            $column('occurrences').' AS occurrences',
            $column('lastOccurredAt').' AS last_occurred_at',
        ]).' FROM '.$metadata->getTableName().' a'.$this->whereClause($where);
    }

    private function changePart(
        string $table,
        string $class,
        string $alias,
        ActivityJournalFilter $filter,
        array &$params,
        array &$types,
    ): string {
        $platform = $this->entityManager->getConnection()->getDatabasePlatform();
        $where = [];
        $params[$alias.'_class'] = $class;

        if ($filter->subject) {
            $where[] = $alias.'.object_id = :'.$alias.'_subject_id'
                .' AND ('.$alias.'.discriminator IS NULL OR '.$alias.'.discriminator = :'.$alias.'_subject_type)';
            $params[$alias.'_subject_id'] = $filter->subject->id;
            $params[$alias.'_subject_type'] = $filter->subject->type;
        }

        if ($filter->type !== null) {
            $where[] = $alias.'.type = :'.$alias.'_type';
            $params[$alias.'_type'] = substr($filter->type, strlen(ActivityCategory::CHANGE->value) + 1);
        }

        if ($filter->actor) {
            $where[] = $alias.'.blame_id = :'.$alias.'_actor_id';
            $params[$alias.'_actor_id'] = $filter->actor->id;
        }

        $this->wherePeriod($where, $alias.'.created_at', $alias, $filter, $params, $types);

        return 'SELECT '.implode(', ', [
            "'".self::SOURCE_CHANGE."' AS source",
            $alias.'.id AS sort_number',
            'CAST(NULL AS TEXT) AS sort_text',
            "'".ActivityCategory::CHANGE->value."' AS category",
            $platform->getConcatExpression("'".ActivityCategory::CHANGE->value.".'", $alias.'.type').' AS type',
            'COALESCE('.$alias.'.discriminator, :'.$alias.'_class) AS subject_type',
            $alias.'.object_id AS subject_id',
            'CAST(NULL AS TEXT) AS actor_type',
            $alias.'.blame_id AS actor_id',
            $alias.'.blame_user AS actor_name',
            'CAST('.$alias.'.diffs AS TEXT) AS payload',
            $alias.'.created_at AS occurred_at',
            'CAST(1 AS INTEGER) AS occurrences',
            'CAST(NULL AS TIMESTAMP) AS last_occurred_at',
        ]).' FROM '.$table.' '.$alias.$this->whereClause($where);
    }

    private function wherePeriod(
        array &$where,
        string $column,
        string $alias,
        ActivityJournalFilter $filter,
        array &$params,
        array &$types,
    ): void {
        foreach (['from' => '>=', 'to' => '<'] as $bound => $operator) {
            if ($filter->{$bound}) {
                $where[] = $column.' '.$operator.' :'.$alias.'_'.$bound;
                $params[$alias.'_'.$bound] = $filter->{$bound}->setTimezone(new DateTimeZone('UTC'));
                $types[$alias.'_'.$bound] = Types::DATETIME_IMMUTABLE;
            }
        }
    }

    private function whereClause(array $where): string
    {
        return $where ? ' WHERE '.implode(' AND ', $where) : '';
    }

    /**
     * A UUID as text ordered as its bytes: native on PostgreSQL, sixteen
     * bytes elsewhere, written in hexadecimal.
     */
    private function sortableUuid(string $column): string
    {
        return $this->entityManager->getConnection()->getDatabasePlatform() instanceof PostgreSQLPlatform
            ? 'CAST('.$column.' AS TEXT)'
            : 'HEX('.$column.')';
    }

    private function hydrate(array $row): ActivityEntry
    {
        $payload = json_decode((string) $row['payload'], true) ?: [];
        $occurredAt = new DateTimeImmutable($row['occurred_at'], new DateTimeZone('UTC'));

        if ($row['source'] === self::SOURCE_ACTIVITY) {
            return new ActivityEntry(
                $row['category'],
                $row['type'],
                $row['subject_type'],
                $row['subject_id'],
                $row['actor_type'],
                $row['actor_id'],
                $payload,
                $occurredAt,
                occurrences: (int) $row['occurrences'],
                lastOccurredAt: $row['last_occurred_at'] !== null
                    ? new DateTimeImmutable($row['last_occurred_at'], new DateTimeZone('UTC'))
                    : null,
            );
        }

        return new ActivityEntry(
            $row['category'],
            $row['type'],
            $row['subject_type'],
            $row['subject_id'],
            null,
            $row['actor_id'],
            ActivityChangeHelper::context($payload),
            $occurredAt,
            ActivityChangeHelper::changes(substr($row['type'], strlen(ActivityCategory::CHANGE->value) + 1), $payload),
            actorLabel: $row['actor_name'],
        );
    }

    /**
     * Names what each entry is about, and who did it when someone else than
     * its subject did: what a screen shows in place of a type and an id.
     *
     * @param list<ActivityEntry> $entries
     *
     * @return list<ActivityEntry>
     */
    private function label(array $entries): array
    {
        return array_map(fn (ActivityEntry $entry) => $entry->withLabels(
            $this->subjects->label($entry->getSubjectType(), $entry->getSubjectId()),
            $entry->getActorLabel() ?? ($entry->getActorType() !== null
                ? $this->subjects->label($entry->getActorType(), $entry->getActorId())
                : null),
        ), $entries);
    }
}
