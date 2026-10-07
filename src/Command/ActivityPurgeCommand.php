<?php

namespace Wexample\SymfonyActivity\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wexample\SymfonyActivity\Service\ActivityPurgeService;

/**
 * The retention of every category, applied. To schedule — once a day is the
 * grain the retentions are counted in.
 */
#[AsCommand(name: 'activity:purge', description: 'Delete or anonymize the activity entries past the retention of their category.')]
class ActivityPurgeCommand extends Command
{
    public function __construct(
        private readonly ActivityPurgeService $purgeService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $purged = $this->purgeService->purge();

        if (! $purged) {
            $io->note('No category has a retention: nothing expires.');

            return Command::SUCCESS;
        }

        foreach ($purged as $category => $count) {
            $io->writeln(sprintf('%s: %d', $category, $count));
        }

        return Command::SUCCESS;
    }
}
