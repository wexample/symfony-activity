<?php

namespace Wexample\SymfonyActivity\Service;

use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;

/**
 * Where auditor keeps the changes of each entity marked `#[Auditable]`: one
 * `<table>_audit` table per entity table. They are read on the connection of
 * the activity table — auditor's storage is the default entity manager, as
 * the activity table is.
 */
class ActivityChangeTablesService
{
    public function __construct(
        private readonly DoctrineProvider $provider,
        private readonly Reader $reader,
    ) {
    }

    /**
     * Each audit table and the class its rows are about when they carry no
     * discriminator: entities sharing a table — a single-table inheritance —
     * share its audit table too, read once.
     *
     * @return array<string, class-string> audit table => entity class
     */
    public function getTables(): array
    {
        $tables = [];

        foreach (array_keys($this->provider->getConfiguration()->getEntities()) as $class) {
            $tables[$this->reader->getEntityAuditTableName($class)] ??= $class;
        }

        return $tables;
    }

    /**
     * The audit table holding the changes of $type, or null when it is not
     * audited.
     */
    public function getTable(string $type): ?string
    {
        return $this->provider->isAuditable($type)
            ? $this->reader->getEntityAuditTableName($type)
            : null;
    }
}
