<?php

namespace Wexample\SymfonyActivity\Class;

use DateTimeImmutable;

/**
 * A value a field held, read from its changes: what it was set to, when and
 * by whom. The value it had before its changes were kept knows neither.
 */
final class ActivityFieldVersion
{
    public function __construct(
        public readonly mixed $value,
        public readonly ?DateTimeImmutable $date = null,
        // The id symfony-activity names an actor with, and the name auditor
        // read: none for a change made outside any request, or before.
        public readonly ?string $authorId = null,
        public readonly ?string $authorName = null,
    ) {
    }
}
