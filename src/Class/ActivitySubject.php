<?php

namespace Wexample\SymfonyActivity\Class;

/**
 * Whose history it is, by type and id: what an entry stores, and what a
 * history is read with. ActivitySubjectService builds it from an object; a
 * caller holding only the id — an event carrying a user id — builds it
 * itself.
 */
final readonly class ActivitySubject
{
    public function __construct(
        public string $type,
        public string $id,
    ) {
    }
}
