<?php

namespace Wexample\SymfonyActivity\Class;

use DateTimeImmutable;

/**
 * What a reading of the journal narrows to, each criterion left null to keep
 * everything: whose history, which category or type, done by whom, within
 * which period — `from` included, `to` excluded, both UTC.
 *
 * The actor is who did it: the actor of an entry, or its subject when nobody
 * else is named — the subject of its own history did it itself.
 */
final readonly class ActivityJournalFilter
{
    public function __construct(
        public ?ActivitySubject $subject = null,
        public ?string $category = null,
        public ?string $type = null,
        public ?ActivitySubject $actor = null,
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
    ) {
    }
}
