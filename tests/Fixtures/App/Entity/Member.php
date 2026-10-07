<?php

namespace Wexample\SymfonyActivity\Tests\Fixtures\App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;

/**
 * A signed-in user that is an entity, whose pages can be recorded.
 */
#[ORM\Entity]
class Member extends AbstractEntity implements UserInterface
{
    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    private string $email = '';

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }
}
