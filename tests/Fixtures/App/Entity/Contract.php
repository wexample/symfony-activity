<?php

namespace Wexample\SymfonyActivity\Tests\Fixtures\App\Entity;

use DH\Auditor\Provider\Doctrine\Auditing\Annotation\Auditable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;

/**
 * An entity whose field changes are kept, by its mark alone.
 */
#[ORM\Entity]
#[Auditable]
class Contract extends AbstractEntity implements Stringable
{
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $title = '';

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
