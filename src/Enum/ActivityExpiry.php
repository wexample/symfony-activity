<?php

namespace Wexample\SymfonyActivity\Enum;

/**
 * What a category does with an entry past its retention, chosen by the
 * application in `categories.<name>.on_expiry`.
 */
enum ActivityExpiry: string
{
    /** The entry is removed. */
    case DELETE = 'delete';

    /**
     * The entry stays, counted, but tells no more whose it was: its subject,
     * its actor and its context are emptied.
     */
    case ANONYMIZE = 'anonymize';
}
