<?php

namespace Wexample\SymfonyActivity\Service;

use DH\Auditor\User\User;
use DH\Auditor\User\UserInterface;
use DH\Auditor\User\UserProviderInterface;
use DH\AuditorBundle\User\UserProvider;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Who changed a field, for auditor: the signed-in user, by the id
 * symfony-activity names it with — the id of an actor in the activity table,
 * so that the journal finds what someone did in both. Its name, the
 * impersonator included, is auditor's own reading.
 */
class ActivityAuditUserProvider implements UserProviderInterface
{
    private readonly UserProvider $names;

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly ActivitySubjectService $subjects,
    ) {
        $this->names = new UserProvider($tokenStorage);
    }

    public function __invoke(): ?UserInterface
    {
        $named = ($this->names)();
        $user = $this->tokenStorage->getToken()?->getUser();

        if ($user === null || ! $this->subjects->supports($user)) {
            return $named;
        }

        return new User($this->subjects->of($user)->id, $named?->getUsername());
    }
}
