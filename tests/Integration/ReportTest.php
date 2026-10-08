<?php

namespace Wexample\SymfonyActivity\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyActivity\Controller\ActivityReportController;
use Wexample\SymfonyActivity\Entity\Activity;
use Wexample\SymfonyActivity\EventSubscriber\ServerErrorActivitySubscriber;
use Wexample\SymfonyActivity\Service\ActivityVisitorService;
use Wexample\SymfonyActivity\Tests\Fixtures\App\ReportAppKernel;

/**
 * What a browser reports and what the server fails at, kept for a visitor
 * nobody signed in as: merged when repeating, refused entries counted.
 */
class ReportTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    protected static function getKernelClass(): string
    {
        return ReportAppKernel::class;
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        (new SchemaTool($this->entityManager))->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
        // The rate limits count in the application cache, which outlives a test.
        self::getContainer()->get('cache.app')->clear();
    }

    private function report(array $entries): int
    {
        $this->client->request('POST', '/_activity/report', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['entries' => $entries]));

        return $this->client->getResponse()->getStatusCode();
    }

    /**
     * @return list<Activity>
     */
    private function entries(string $category): array
    {
        $this->entityManager->clear();

        return $this->entityManager->getRepository(Activity::class)->findBy(['category' => $category]);
    }

    public function testAReportIsKeptForTheVisitorAndItsRepeatsCounted(): void
    {
        $error = ['category' => 'client', 'type' => 'client.error', 'context' => ['message' => 'x is undefined', 'line' => 12], 'fingerprint' => 'abcdef0123456789'];

        $this->assertSame(204, $this->report([$error, $error]));
        $this->assertSame('client', $this->client->getResponse()->headers->get(ActivityReportController::HEADER_CATEGORIES));
        $this->report([$error]);

        [$entry] = $this->entries('client');
        $this->assertSame(ActivityVisitorService::VISITOR, $entry->getSubjectType());
        $this->assertSame(32, strlen($entry->getSubjectId()));
        $this->assertSame(['message' => 'x is undefined', 'line' => 12], $entry->getContext());
        $this->assertSame(3, $entry->getOccurrences());
        $this->assertNotNull($entry->getLastOccurredAt());
    }

    public function testWhatIsRefusedIsCountedNotLost(): void
    {
        $this->report([
            ['category' => 'client', 'type' => 'Not A Type'],
            ['category' => 'security', 'type' => 'security.login.succeeded'],
            ['category' => 'client', 'type' => 'client.error', 'context' => ['nested' => ['no']]],
            ['category' => 'ui', 'type' => 'ui.form.abandoned'],
        ]);

        // `ui` is open to browsers but disabled: dropped as the application asked, not refused.
        $this->assertSame([], $this->entries('ui'));
        $this->assertSame([], $this->entries('client'));

        [$refused] = $this->entries('security');
        $this->assertSame(ActivityReportController::TYPE_REFUSED, $refused->getType());
        $this->assertSame(['category' => 1, 'context' => 1, 'type' => 1], $refused->getContext());

        $this->client->request('POST', '/_activity/report', content: '{"entries": []}');
        $this->assertSame(415, $this->client->getResponse()->getStatusCode());
        $this->assertSame(400, $this->report(['not' => 'a list']));
    }

    public function testAFloodIsRefusedAndRecordedOnce(): void
    {
        $statuses = [];
        for ($i = 0; $i < 62; ++$i) {
            $statuses[] = $this->report([]);
        }

        $this->assertSame(429, end($statuses));
        [$limited] = $this->entries('security');
        $this->assertSame('security.rate_limit.exceeded', $limited->getType());
        $this->assertSame('activity_report', $limited->getContext()['limit']);
        $this->assertSame(2, $limited->getOccurrences());
    }

    public function testServerErrorsAreKeptAndClientErrorsAreNot(): void
    {
        $this->client->request('GET', '/boom');
        $this->client->request('GET', '/boom');
        $this->client->request('GET', '/gone');
        $this->assertSame(404, $this->client->getResponse()->getStatusCode());

        [$entry] = $this->entries('server');
        $this->assertSame(ServerErrorActivitySubscriber::TYPE, $entry->getType());
        $this->assertSame('RuntimeException', $entry->getContext()['class']);
        $this->assertSame('The fixture fails.', $entry->getContext()['message']);
        $this->assertSame(500, $entry->getContext()['status']);
        $this->assertSame('boom', $entry->getContext()['route']);
        $this->assertStringStartsNotWith('/', $entry->getContext()['file']);
        $this->assertSame(2, $entry->getOccurrences());
    }
}
