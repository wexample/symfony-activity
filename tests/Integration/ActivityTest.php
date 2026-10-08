<?php

namespace Wexample\SymfonyActivity\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Wexample\SymfonyActivity\Class\ActivityEntry;
use Wexample\SymfonyActivity\Class\ActivitySubject;
use Wexample\SymfonyActivity\Interface\ActivitySubjectInterface;
use Wexample\SymfonyActivity\Repository\ActivityRepository;
use Wexample\SymfonyActivity\Service\ActivityRecorder;
use Wexample\SymfonyActivity\Tests\Fixtures\App\Entity\Customer;

/**
 * `security` kept a year then anonymized, `support` kept thirty days then
 * deleted, `billing` declared but disabled, anything else undeclared.
 */
class ActivityTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private ActivityRecorder $recorder;

    private ActivityRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        (new SchemaTool($this->entityManager))->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        $this->recorder = self::getContainer()->get('test.activity_recorder');
        $this->repository = self::getContainer()->get('test.activity_repository');
    }

    public function testAnEnabledCategoryIsRecordedAgainstTheEntity(): void
    {
        $customer = $this->createCustomer();

        $this->recorder->record($customer, 'support', 'support.contacted', ['channel' => 'chat']);

        [$entry] = $this->history($customer);
        $this->assertSame(Customer::class, $entry->getSubjectType());
        $this->assertSame((string) $customer->getId(), $entry->getSubjectId());
        $this->assertSame('support.contacted', $entry->getType());
        $this->assertSame(['channel' => 'chat'], $entry->getContext());
        $this->assertSame('UTC', $entry->getOccurredAt()->getTimezone()->getName());
    }

    public function testNothingIsKeptOfACategoryTheApplicationDidNotAskFor(): void
    {
        $customer = $this->createCustomer();

        $this->recorder->record($customer, 'billing', 'billing.invoice_paid');
        $this->recorder->record($customer, 'page', 'page.viewed');
        $this->recorder->record($customer, 'anything', 'anything.happened');

        $this->assertSame([], $this->history($customer));
        $this->assertFalse($this->recorder->records('page'));
    }

    /**
     * The entry is written straight away, and what the caller has pending in
     * its unit of work is left to the caller.
     */
    public function testRecordingFlushesNothingOfTheCaller(): void
    {
        $customer = $this->createCustomer();
        $pending = new Customer();
        $this->entityManager->persist($pending);

        $this->recorder->record($customer, 'support', 'support.contacted');

        $this->assertTrue($this->entityManager->getUnitOfWork()->isScheduledForInsert($pending));
        $this->assertCount(1, $this->history($customer));
    }

    public function testASubjectThatIsNoEntityNamesItself(): void
    {
        $subject = new class () implements ActivitySubjectInterface {
            public function getActivitySubjectType(): string
            {
                return 'stripe_customer';
            }

            public function getActivitySubjectId(): string
            {
                return 'cus_42';
            }
        };

        $this->recorder->record($subject, 'support', 'support.contacted', actor: $this->createCustomer());

        [$entry] = $this->repository->findRecent(new ActivitySubject('stripe_customer', 'cus_42'), 10);
        $this->assertSame(Customer::class, $entry->getActorType());
    }

    public function testThePurgeDeletesOrAnonymizesAsEachCategorySays(): void
    {
        $customer = $this->createCustomer();
        $this->recorder->record($customer, 'support', 'support.contacted');
        $this->recorder->record($customer, 'security', 'security.login.succeeded', ['ip' => '192.0.2.1']);

        // Four hundred days later, past both retentions.
        $purged = self::getContainer()->get('test.activity_purge')
            ->purge(new DateTimeImmutable('+400 days', new DateTimeZone('UTC')));

        $this->assertSame(['security' => 1, 'support' => 1, 'change' => 0], $purged);
        $this->assertSame([], $this->history($customer));

        $this->entityManager->clear();
        [$anonymized] = $this->repository->findAll();
        $this->assertSame('security.login.succeeded', $anonymized->getType());
        $this->assertNull($anonymized->getSubjectId());
        $this->assertSame([], $anonymized->getContext());
    }

    public function testTheRetentionIsNotReachedYet(): void
    {
        $customer = $this->createCustomer();
        $this->recorder->record($customer, 'support', 'support.contacted');

        $purged = self::getContainer()->get('test.activity_purge')
            ->purge(new DateTimeImmutable('+10 days', new DateTimeZone('UTC')));

        $this->assertSame(['security' => 0, 'support' => 0, 'change' => 0], $purged);
        $this->assertCount(1, $this->history($customer));
    }

    public function testAPersonGetsTheirHistoryAndHasItErased(): void
    {
        $customer = $this->createCustomer();
        $other = $this->createCustomer();
        $this->recorder->record($customer, 'support', 'support.contacted');
        $this->recorder->record($customer, 'security', 'security.login.succeeded');
        // Something the customer did to someone else.
        $this->recorder->record($other, 'support', 'support.answered', actor: $customer);

        $data = self::getContainer()->get('test.activity_subject_data');

        $export = $data->export($customer);
        $this->assertSame(
            ['support.contacted', 'security.login.succeeded', 'support.answered'],
            array_column($export, 'type')
        );
        // What it did to someone else names what it was about, not whom.
        $this->assertSame([null, null, Customer::class], array_column($export, 'about_someone_else'));

        $this->assertSame(2, $data->erase($customer));
        $this->assertSame([], $this->history($customer));

        [$kept] = $this->history($other);
        $this->assertNull($kept->getActorId());
    }

    public function testTheChangeCategoryIsNotRecordedByHand(): void
    {
        $this->expectException(LogicException::class);

        $this->recorder->record($this->createCustomer(), 'change', 'change.update');
    }

    /**
     * @return list<ActivityEntry>
     */
    private function history(object $subject): array
    {
        $this->entityManager->clear();

        return $this->repository->findRecent(
            self::getContainer()->get('test.activity_subjects')->of($subject),
            100
        );
    }

    private function createCustomer(): Customer
    {
        $customer = new Customer();
        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        return $customer;
    }
}
