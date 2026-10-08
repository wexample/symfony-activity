<?php

namespace Wexample\SymfonyActivity\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Wexample\SymfonyActivity\Class\ActivitySubject;

/**
 * Whose history what the current request did belongs to: the signed-in
 * user, when the package can name them; a visitor otherwise, so that what
 * nobody signed in did is kept too.
 *
 * A visitor is named by a keyed hash of their session, or of their address
 * and browser when they have none: the same visitor reads as the same id
 * through a visit, and the id gives neither back.
 */
class ActivityVisitorService
{
    public const string VISITOR = 'visitor';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ActivitySubjectService $subjects,
        #[Autowire(param: 'kernel.secret')]
        private readonly string $secret,
        private readonly ?TokenStorageInterface $tokenStorage = null,
    ) {
    }

    public function current(): object
    {
        $user = $this->tokenStorage?->getToken()?->getUser();

        if ($user !== null && $this->subjects->supports($user)) {
            return $user;
        }

        return new ActivitySubject(self::VISITOR, $this->visitorId());
    }

    private function visitorId(): string
    {
        $request = $this->requestStack->getMainRequest();
        $session = $request?->hasSession() ? $request->getSession() : null;
        $seed = $session?->isStarted()
            ? 'session:'.$session->getId()
            : 'client:'.$request?->getClientIp().'|'.$request?->headers->get('User-Agent');

        return substr(hash_hmac('sha256', $seed, $this->secret), 0, 32);
    }
}
