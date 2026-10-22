<?php

namespace Wexample\SymfonyActivity\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Wexample\SymfonyActivity\Enum\ActivityCategory;
use Wexample\SymfonyActivity\Service\ActivityRecorder;
use Wexample\SymfonyActivity\Service\ActivityVisitorService;
use Wexample\SymfonySecurity\Event\RateLimitSecurityEvent;

/**
 * Each request a #[RateLimit] refused, in the `security` category, against
 * whoever made it: what is refused is lost to the application, and has to
 * show somewhere. With `merge_within`, a flood is one entry counting.
 */
class RateLimitActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ActivityRecorder $recorder,
        private readonly ActivityVisitorService $visitors,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [RateLimitSecurityEvent::class => 'onRateLimit'];
    }

    public function onRateLimit(RateLimitSecurityEvent $event): void
    {
        $limit = (string) $event->extra['limit'];

        $this->recorder->record(
            $this->visitors->current(),
            ActivityCategory::SECURITY->value,
            ActivityCategory::SECURITY->value.'.'.$event->type->value,
            [
                'limit' => $limit,
                'route' => $event->extra['route'],
                'method' => $event->method,
                'request_id' => $event->requestId,
            ],
            fingerprint: hash('sha256', $event->type->value.'|'.$limit),
        );
    }
}
