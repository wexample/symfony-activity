<?php

namespace Wexample\SymfonyActivity\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyActivity\EventSubscriber\PageViewSubscriber;
use Wexample\SymfonyActivity\Tests\Fixtures\App\Entity\Member;
use Wexample\SymfonyActivity\Tests\Fixtures\App\PageAppKernel;

/**
 * `page` enabled: what a signed-in member reads, and nothing else.
 */
class PageViewTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    private Member $member;

    protected static function getKernelClass(): string
    {
        return PageAppKernel::class;
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        (new SchemaTool($this->entityManager))->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        $this->member = (new Member())->setEmail('jane@example.com');
        $this->entityManager->persist($this->member);
        $this->entityManager->flush();
    }

    public function testAPageReadBySomeoneSignedInIsRecordedWithoutItsQuery(): void
    {
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/page?token=secret');

        [$entry] = $this->history();
        $this->assertSame(PageViewSubscriber::TYPE, $entry->getType());
        $this->assertSame(['route' => 'page', 'path' => '/page'], $entry->getContext());
    }

    public function testWhatIsNoPageReadIsNotRecorded(): void
    {
        // Nobody signed in.
        $this->client->request('GET', '/page');

        $this->client->loginUser($this->member);
        $this->client->request('GET', '/data');
        $this->client->request('GET', '/_program');
        $this->client->request('POST', '/page');
        $this->client->request('GET', '/missing');

        $this->assertSame([], $this->history());
    }

    private function history(): array
    {
        $this->entityManager->clear();

        return self::getContainer()->get('test.activity_repository')->findRecent(
            self::getContainer()->get('test.activity_subjects')->of($this->member),
            100
        );
    }
}
