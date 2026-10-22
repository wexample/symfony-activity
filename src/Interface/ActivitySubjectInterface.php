<?php

namespace Wexample\SymfonyActivity\Interface;

/**
 * What can have a history without being a Doctrine entity — an entity needs
 * nothing, its class and its id name it.
 */
interface ActivitySubjectInterface
{
    /**
     * A name stable over time: the class of an entity, or a key of the
     * application's own (`stripe_customer`).
     */
    public function getActivitySubjectType(): string;

    public function getActivitySubjectId(): string;
}
