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
}
