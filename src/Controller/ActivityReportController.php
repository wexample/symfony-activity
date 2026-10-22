<?php

namespace Wexample\SymfonyActivity\Controller;

use InvalidArgumentException;
use JsonException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Wexample\SymfonyActivity\Enum\ActivityCategory;
use Wexample\SymfonyActivity\Helper\ActivityReportHelper;
use Wexample\SymfonyActivity\Service\ActivityPolicyService;
use Wexample\SymfonyActivity\Service\ActivityRecorder;
use Wexample\SymfonyActivity\Service\ActivityVisitorService;
use Wexample\SymfonySecurity\Attribute\RateLimit;

/**
 * Where a browser reports what happened in it — its errors, what its
 * interface tells —, a batch at a time, into the categories the
 * application opened to it (`client_categories`).
 *
 * Anyone may post here, signed in or not, so what is accepted is bounded:
 * JSON only, which a page of another site cannot send without asking first;
 * a limited batch, a bounded entry (ActivityReportHelper), a rate per
 * address. What is refused is recorded too, counted in `security`: a
 * report lost without a trace would make the whole activity untrustworthy.
 */
#[Route(path: '/_activity/report', name: 'activity_report', methods: [Request::METHOD_POST])]
#[RateLimit(60, '1 minute', name: 'activity_report')]
final class ActivityReportController
{
    public const int MAX_ENTRIES = 20;

    public const string TYPE_REFUSED = 'security.activity_report.refused';

    /**
     * Answered with every report: the categories still recorded, so that a
     * browser stops sending what would be dropped.
     */
    public const string HEADER_CATEGORIES = 'X-Activity-Categories';

    /**
     * @param list<string> $clientCategories
     */
    public function __construct(
        private readonly ActivityRecorder $recorder,
        private readonly ActivityPolicyService $policy,
        private readonly ActivityVisitorService $visitors,
        #[Autowire(param: 'wexample_symfony_activity.client_categories')]
        private readonly array $clientCategories = [],
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if ($request->getContentTypeFormat() !== 'json') {
            return new Response(null, Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        try {
            $entries = json_decode($request->getContent(), true, 8, JSON_THROW_ON_ERROR)['entries'] ?? null;
        } catch (JsonException) {
            $entries = null;
        }

        if (! is_array($entries) || ! array_is_list($entries)) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        $subject = $this->visitors->current();
        $refused = [];

        foreach (array_slice($entries, self::MAX_ENTRIES) as $ignored) {
            $refused['batch'] = ($refused['batch'] ?? 0) + 1;
        }

        foreach (array_slice($entries, 0, self::MAX_ENTRIES) as $entry) {
            try {
                $entry = ActivityReportHelper::read($entry);
            } catch (InvalidArgumentException $exception) {
                $refused[$exception->getMessage()] = ($refused[$exception->getMessage()] ?? 0) + 1;

                continue;
            }

            if (! in_array($entry['category'], $this->clientCategories, true)) {
                $refused['category'] = ($refused['category'] ?? 0) + 1;

                continue;
            }

            $this->recorder->record(
                $subject,
                $entry['category'],
                $entry['type'],
                $entry['context'],
                fingerprint: $entry['fingerprint'],
            );
        }

        if ($refused !== []) {
            ksort($refused);
            $this->recorder->record(
                $subject,
                ActivityCategory::SECURITY->value,
                self::TYPE_REFUSED,
                $refused,
                fingerprint: hash('sha256', self::TYPE_REFUSED),
            );
        }

        $response = new Response(null, Response::HTTP_NO_CONTENT);
        $response->headers->set(self::HEADER_CATEGORIES, implode(',', array_values(array_filter(
            $this->clientCategories,
            $this->policy->isEnabled(...)
        ))));

        return $response;
    }
}
