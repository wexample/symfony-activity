<?php

namespace Wexample\SymfonyActivity\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Wexample\SymfonyActivity\Enum\ActivityCategory;
use Wexample\SymfonyActivity\Service\ActivityRecorder;
use Wexample\SymfonyActivity\Service\ActivitySubjectService;

/**
 * Each page a signed-in user reads, as `page.viewed` in the `page` category,
 * when the application enabled it. A page is what a reader asked for and got:
 * a GET answered with HTML, not under `/_` — where a program asks, not a
 * reader —, nor an error. The path is kept without its query, which carries
 * searches and, now and then, a token.
 *
 * The heaviest category by far: give it a short retention. A signed-in user
 * the package cannot name — neither an entity nor an ActivitySubjectInterface
 * — is skipped rather than refused a page.
 */
class PageViewSubscriber implements EventSubscriberInterface
{
    public const string TYPE = 'page.viewed';

    public function __construct(
        private readonly ActivityRecorder $recorder,
        private readonly ActivitySubjectService $subjects,
        private readonly ?TokenStorageInterface $tokenStorage = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (! $event->isMainRequest() || ! $this->recorder->records(ActivityCategory::PAGE->value)) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        $user = $this->tokenStorage?->getToken()?->getUser();
        // Unset until the response is sent, for a page rendered without one:
        // Symfony makes it text/html then.
        $contentType = $response->headers->get('Content-Type');

        // A user that is neither an entity nor an ActivitySubjectInterface —
        // one of a memory provider — has no history to write into.
        if ($user === null
            || ! $this->subjects->supports($user)
            || ! $request->isMethod(Request::METHOD_GET)
            || ! $response->isSuccessful()
            || ($contentType !== null && ! str_contains($contentType, 'text/html'))
            || str_starts_with($request->getPathInfo(), '/_')) {
            return;
        }

        $this->recorder->record($user, ActivityCategory::PAGE->value, self::TYPE, [
            'route' => $request->attributes->get('_route'),
            'path' => $request->getPathInfo(),
        ]);
    }
}
