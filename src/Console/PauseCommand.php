<?php

declare(strict_types=1);

namespace WatchDiff\Core\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pause', description: 'Pause a watcher by id, URL, or tag.')]
final class PauseCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('tagOrId', InputArgument::REQUIRED)
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to the config file. Env: WATCHDIFF_CONFIG');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $watchDiff = WatchDiffFactory::create();
        foreach (ConfigFile::load(EnvOption::stringOr($input, 'config', 'WATCHDIFF_CONFIG', 'watchdiff.config.json')) as $watcher) {
            $watchDiff->watch($watcher);
        }

        $watchDiff->pause($input->getArgument('tagOrId'));
        $output->writeln(sprintf('<info>Paused watcher(s) matching "%s".</info>', $input->getArgument('tagOrId')));

        return Command::SUCCESS;
    }
}
