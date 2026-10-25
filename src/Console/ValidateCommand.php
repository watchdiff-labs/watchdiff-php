<?php

declare(strict_types=1);

namespace WatchDiff\Core\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'validate', description: 'Validate a watchdiff config file.')]
final class ValidateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to the config file. Env: WATCHDIFF_CONFIG')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print the result as JSON. Env: WATCHDIFF_JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errors = ConfigFile::validate(EnvOption::stringOr($input, 'config', 'WATCHDIFF_CONFIG', 'watchdiff.config.json'));
        $json   = EnvOption::bool($input, 'json', 'WATCHDIFF_JSON');

        if ($json) {
            $output->writeln(json_encode(['valid' => [] === $errors, 'errors' => $errors], JSON_UNESCAPED_SLASHES) ?: '{}');

            return [] === $errors ? Command::SUCCESS : Command::FAILURE;
        }

        if ([] === $errors) {
            $output->writeln('<info>Config is valid.</info>');

            return Command::SUCCESS;
        }

        foreach ($errors as $error) {
            $output->writeln(sprintf('<error>%s</error>', $error));
        }

        return Command::FAILURE;
    }
}
