<?php

namespace Wexample\SymfonyActivity\Enum;

/**
 * The categories the suite's own packages record in. An application adds its
 * own by naming them in `categories`; nothing has to be declared here.
 */
enum ActivityCategory: string
{
    /** Signing in, failing to, the second factor, a password changed — symfony-user. */
    case SECURITY = 'security';

    /** A page read by a signed-in user — PageViewSubscriber. */
    case PAGE = 'page';

    /** A mail sent to the subject. */
    case MAIL = 'mail';

    /**
     * A field changed on an entity marked `#[Auditable]`, recorded by
     * auditor-bundle in its own tables and read here with the rest. Declaring
     * it in `categories` only sets its retention: what is recorded is what the
     * entities ask for.
     */
    case CHANGE = 'change';

    /** An error the server could not answer through: a 5xx — ServerErrorActivitySubscriber. */
    case SERVER = 'server';

    /** An error in the browser, reported by symfony-activity-ds. */
    case CLIENT = 'client';

    /** What the interface tells of its use — a form left unsent —, reported by symfony-activity-ds. */
    case UI = 'ui';
}
