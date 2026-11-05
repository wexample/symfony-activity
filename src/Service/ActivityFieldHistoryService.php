<?php

namespace Wexample\SymfonyActivity\Service;

use DH\Auditor\Model\Entry;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use Wexample\SymfonyActivity\Class\ActivityFieldVersion;

/**
 * The values one field of an entity held, read back from its changes: a
 * content's earlier versions, and who wrote each — a contributor, an agent
 * acting for one. Only an entity marked `#[Auditable]` has any; its value
 * before the marking is the first change's old one, of no date nor author.
 */
class ActivityFieldHistoryService
{
    public function __construct(
        private readonly Reader $reader,
        private readonly ActivityChangeTablesService $tables,
        private readonly ActivitySubjectService $subjects,
    ) {
    }

    /**
     * The values $field held before the one it holds, newest first.
     *
     * @return ActivityFieldVersion[]
     */
    public function findEarlierVersions(object $entity, string $field): array
    {
        $subject = $this->subjects->of($entity);

        if ($this->tables->getTable($subject->type) === null) {
            return [];
        }

        $entries = array_reverse($this->reader->createQuery($subject->type, ['object_id' => $subject->id])->execute());
        $versions = [];

        foreach ($entries as $entry) {
            /** @var Entry $entry */
            $diff = $entry->getDiffs()[$field] ?? null;

            if (! is_array($diff) || ! array_key_exists('new', $diff)) {
                continue;
            }

            // Changed before it was audited: what it held then, from nobody.
            if ($versions === [] && $entry->getType() !== 'insert' && ($diff['old'] ?? null) !== null) {
                $versions[] = new ActivityFieldVersion($diff['old']);
            }

            $versions[] = new ActivityFieldVersion(
                $diff['new'],
                $entry->getCreatedAt(),
                $entry->getUserId() === null ? null : (string) $entry->getUserId(),
                $entry->getUsername(),
            );
        }

        // The last is the one it holds.
        array_pop($versions);

        return array_reverse($versions);
    }
}
