<?php

declare(strict_types=1);

namespace WatchDiff\Core\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(name: 'clean', description: 'Delete stored snapshots for watchers no longer in the config file.')]
final class CleanCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to the config file. Env: WATCHDIFF_CONFIG')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip the confirmation prompt. Env: WATCHDIFF_YES');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $watchDiff      = WatchDiffFactory::create();
        $configuredKeys = array_map(
            static fn ($watcher) => $watcher->key(),
            ConfigFile::load(EnvOption::stringOr($input, 'config', 'WATCHDIFF_CONFIG', 'watchdiff.config.json')),
        );

        $orphans = array_diff($watchDiff->storedKeys(), $configuredKeys);

        if ([] === $orphans) {
            $output->writeln('<info>Nothing to clean.</info>');

            return Command::SUCCESS;
        }

        if (!EnvOption::bool($input, 'yes', 'WATCHDIFF_YES')) {
            $output->writeln(sprintf('<comment>%d orphan watcher(s) will be deleted:</comment>', \count($orphans)));
            foreach ($orphans as $orphan) {
                $output->writeln(' - '.$orphan);
            }

            if (!$input->isInteractive()) {
                $output->writeln('<error>Refusing to delete without confirmation in non-interactive mode. Pass --yes to proceed.</error>');

                return Command::FAILURE;
            }

            /** @var QuestionHelper $helper */
            $helper    = $this->getHelper('question');
            $confirmed = $helper->ask($input, $output, new ConfirmationQuestion('Proceed? [y/N] ', false));

            if (!$confirmed) {
                $output->writeln('<info>Aborted, nothing was deleted.</info>');

                return Command::SUCCESS;
            }
        }

        foreach ($orphans as $orphan) {
            $watchDiff->clear($orphan);
            $output->writeln(sprintf('Removed orphan data for "%s".', $orphan));
        }

        return Command::SUCCESS;
    }
}
