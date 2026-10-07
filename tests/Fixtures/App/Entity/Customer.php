<?php

namespace Wexample\SymfonyActivity\Tests\Fixtures\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;

/**
 * A subject that is nothing but an entity: its class and id name it.
 */
#[ORM\Entity]
class Customer extends AbstractEntity
{
}
