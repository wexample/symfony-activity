<?php

namespace Wexample\SymfonyActivity\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Wexample\SymfonyActivity\Class\ActivityEntry;
use Wexample\SymfonyActivity\Class\ActivityJournalFilter;
use Wexample\SymfonyActivity\Service\ActivityJournalService;
use Wexample\SymfonyActivity\Service\ActivityRecorder;
use Wexample\SymfonyActivity\Service\ActivitySubjectService;
use Wexample\SymfonyActivity\Tests\Fixtures\App\Entity\Contract;
use Wexample\SymfonyActivity\Tests\Fixtures\App\Entity\Customer;
use Wexample\SymfonyActivity\Tests\Fixtures\App\Entity\Member;

/**
 * A Contract marked #[Auditable], changed by a signed-in member: its field
 * changes read with the events, in one journal.
 */
class ChangeJournalTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private ActivityRecorder $recorder;

    private ActivityJournalService $journal;

    private ActivitySubjectService $subjects;

    private Member $member;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        (new SchemaTool($this->entityManager))->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        $this->recorder = self::getContainer()->get('test.activity_recorder');
        $this->journal = self::getContainer()->get('test.activity_journal');
        $this->subjects = self::getContainer()->get('test.activity_subjects');

        $this->member = (new Member())->setEmail('jane@example.com');
        $this->entityManager->persist($this->member);
        $this->entityManager->flush();
    }

    public function testTheChangesOfAnAuditedEntityReadInItsHistoryWithItsEvents(): void
    {
        $this->signIn();
        $contract = $this->createContract('Draft');
        $contract->setTitle('Signed');
        $this->entityManager->flush();
        $this->recorder->record($contract, 'support', 'support.contacted');

        $history = $this->history($contract);

        // Written within one second, which is as fine as both tables keep
        // time: the changes come together, newest first, then the event.
        $this->assertSame(['change.update', 'change.insert', 'support.contacted'], $this->types($history));
        [$update] = $history;
        $this->assertTrue($update->isChange());
        $this->assertSame([['field' => 'title', 'old' => 'Draft', 'new' => 'Signed']], $update->getChanges());
        // Made by the member signed in, named as symfony-activity names it.
        $this->assertSame($this->subjects->of($this->member)->id, $update->getActorId());
        $this->assertSame('jane@example.com', $update->getActorLabel());
        $this->assertSame('Signed', $update->getSubjectLabel());
    }

    public function testTheJournalNarrowsEverySubjectByCategoryTypeActorAndPeriod(): void
    {
        $customer = $this->createCustomer();
        $this->recorder->record($customer, 'support', 'support.contacted');
        $this->recorder->record($customer, 'support', 'support.answered', actor: $this->member);
        $this->signIn();
        $this->createContract('Draft');

        $this->assertSame(3, $this->journal->count(new ActivityJournalFilter()));
        $this->assertSame(['change', 'support'], $this->journal->findCategories(new ActivityJournalFilter()));
        $this->assertSame(['change.insert'], $this->types($this->read(new ActivityJournalFilter(category: 'change'))));
        $this->assertSame(['support.answered'], $this->types($this->read(new ActivityJournalFilter(type: 'support.answered'))));

        // What the member did: an event naming them, a change they made.
        $this->assertSame(
            ['change.insert', 'support.answered'],
            $this->types($this->read(new ActivityJournalFilter(actor: $this->subjects->of($this->member))))
        );

        $tomorrow = new DateTimeImmutable('+1 day', new DateTimeZone('UTC'));
        $this->assertSame(0, $this->journal->count(new ActivityJournalFilter(from: $tomorrow)));
        $this->assertSame(3, $this->journal->count(new ActivityJournalFilter(to: $tomorrow)));
    }

    public function testAPageOfTheJournalIsAPageOfBoth(): void
    {
        $this->signIn();
        $contract = $this->createContract('One');

        foreach (['Two', 'Three'] as $title) {
            $contract->setTitle($title);
            $this->entityManager->flush();
            $this->recorder->record($contract, 'support', 'support.contacted');
        }

        $first = $this->journal->paginate(new ActivityJournalFilter(), 1, 2);
        $second = $this->journal->paginate(new ActivityJournalFilter(), 2, 2);
        $third = $this->journal->paginate(new ActivityJournalFilter(), 3, 2);

        $this->assertSame(5, $first['count']);
        $this->assertSame(3, $first['pages_count']);
        // Every entry once, the pages following one another in one order.
        $this->assertSame(
            ['change.update', 'change.update', 'change.insert', 'support.contacted', 'support.contacted'],
            $this->types([...$first['entries'], ...$second['entries'], ...$third['entries']])
        );
        $this->assertSame(
            ['Three', 'Two'],
            array_map(static fn (ActivityEntry $entry) => $entry->getChanges()[0]['new'], $first['entries'])
        );
    }

    public function testAPersonsCopyHoldsTheFieldsTheyChanged(): void
    {
        $this->signIn();
        $contract = $this->createContract('Draft');
        $contract->setTitle('Signed');
        $this->entityManager->flush();

        $export = self::getContainer()->get('test.activity_subject_data')->export($this->member);

        $this->assertSame(['change.insert', 'change.update'], array_column($export, 'type'));
        $this->assertSame([Contract::class, Contract::class], array_column($export, 'about_someone_else'));
        $this->assertSame([['field' => 'title', 'old' => 'Draft', 'new' => 'Signed']], $export[1]['changes']);
    }

    public function testErasingAPersonTakesTheirNameOffTheirChanges(): void
    {
        $this->signIn();
        $contract = $this->createContract('Draft');

        self::getContainer()->get('test.activity_subject_data')->erase($this->member);

        [$insert] = $this->history($contract);
        $this->assertNull($insert->getActorId());
        $this->assertNull($insert->getActorLabel());
    }

    public function testErasingAnAuditedSubjectRemovesItsChanges(): void
    {
        $contract = $this->createContract('Draft');

        $this->assertSame(1, self::getContainer()->get('test.activity_subject_data')->erase($contract));
        $this->assertSame([], $this->history($contract));
    }

    public function testThePurgeAppliesTheRetentionOfChanges(): void
    {
        $contract = $this->createContract('Draft');

        $purged = self::getContainer()->get('test.activity_purge')
            ->purge(new DateTimeImmutable('+100 days', new DateTimeZone('UTC')));

        $this->assertSame(1, $purged['change']);
        $this->assertSame([], $this->history($contract));
    }

    private function signIn(): void
    {
        self::getContainer()->get('test.token_storage')
            ->setToken(new UsernamePasswordToken($this->member, 'main', $this->member->getRoles()));
    }

    private function createContract(string $title): Contract
    {
        $contract = (new Contract())->setTitle($title);
        $this->entityManager->persist($contract);
        $this->entityManager->flush();

        return $contract;
    }

    private function createCustomer(): Customer
    {
        $customer = new Customer();
        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        return $customer;
    }

    /**
     * @return list<ActivityEntry>
     */
    private function history(object $subject): array
    {
        return $this->read(new ActivityJournalFilter(subject: $this->subjects->of($subject)));
    }

    /**
     * @return list<ActivityEntry>
     */
    private function read(ActivityJournalFilter $filter): array
    {
        return $this->journal->findRecent($filter, 100);
    }

    /**
     * @param list<ActivityEntry> $entries
     *
     * @return list<string>
     */
    private function types(array $entries): array
    {
        return array_map(static fn (ActivityEntry $entry) => $entry->getType(), $entries);
    }
}
