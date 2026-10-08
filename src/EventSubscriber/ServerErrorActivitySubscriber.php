<?php

namespace Wexample\SymfonyActivity\EventSubscriber;

use Doctrine\DBAL\Exception as DbalException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Wexample\SymfonyActivity\Enum\ActivityCategory;
use Wexample\SymfonyActivity\Service\ActivityRecorder;
use Wexample\SymfonyActivity\Service\ActivityVisitorService;
use Wexample\SymfonySecurity\Helper\RequestIdHelper;

/**
 * Each request the server could not answer — a 5xx —, as `server.error` in
 * the `server` category, against whoever made it. Its fingerprint is the
 * exception's class and where it was thrown, so that with `merge_within`
 * one failure repeating is one entry counting.
 *
 * The log file keeps them as well: when the database is what failed, the
 * entry cannot be written, and the log is where the failure is told.
 */
class ServerErrorActivitySubscriber implements EventSubscriberInterface
{
    public const string TYPE = 'server.error';

    private const int MESSAGE_LENGTH = 500;

    public function __construct(
        private readonly ActivityRecorder $recorder,
        private readonly ActivityVisitorService $visitors,
        private readonly LoggerInterface $logger,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // After the framework logged it (0), before it answers with the
        // error page (-128), which ends the event.
        return [KernelEvents::EXCEPTION => ['onException', -64]];
    }

    public function onException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        $status = $throwable instanceof HttpExceptionInterface ? $throwable->getStatusCode() : 500;

        if ($status < 500 || ! $this->recorder->records(ActivityCategory::SERVER->value)) {
            return;
        }

        $request = $event->getRequest();
        $file = str_starts_with($throwable->getFile(), $this->projectDir.'/')
            ? substr($throwable->getFile(), strlen($this->projectDir) + 1)
            : $throwable->getFile();

        try {
            $this->recorder->record(
                $this->visitors->current(),
                ActivityCategory::SERVER->value,
                self::TYPE,
                [
                    'class' => $throwable::class,
                    'message' => mb_substr($throwable->getMessage(), 0, self::MESSAGE_LENGTH),
                    'file' => $file,
                    'line' => $throwable->getLine(),
                    'status' => $status,
                    'method' => $request->getMethod(),
                    'route' => $request->attributes->get('_route'),
                    'path' => $request->getPathInfo(),
                    'request_id' => RequestIdHelper::resolve($request),
                ],
                fingerprint: hash('sha256', $throwable::class.'|'.$file.'|'.$throwable->getLine()),
            );
        } catch (DbalException $exception) {
            // The database failing must not hide the error being reported:
            // the framework logged that one already, this says why it is
            // missing from the activity.
            $this->logger->error('The server error could not be recorded as activity: {reason}', [
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
