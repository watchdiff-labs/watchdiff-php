<?php

declare(strict_types=1);

namespace WatchDiff\Core\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use WatchDiff\Core\Model\WatcherStatus;

/**
 * Opt-in, long-running HTTP status server (/health, /status, /metrics).
 * This intentionally does NOT replace the tick/cron model: it does not poll
 * watchers itself, it only reports the state left behind by `watchdiff tick`
 * runs against the same storage. Run it under a process supervisor
 * (systemd, supervisord, ...), never from cron.
 */
#[AsCommand(name: 'serve', description: 'Start a long-running HTTP status server (/health, /status, /metrics).')]
final class ServeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'TCP port to listen on. Env: WATCHDIFF_PORT')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Address to bind to - use 0.0.0.0 to listen on every interface. Env: WATCHDIFF_HOST')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to the config file. Env: WATCHDIFF_CONFIG');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $watchDiff = WatchDiffFactory::create();
        foreach (ConfigFile::load(EnvOption::stringOr($input, 'config', 'WATCHDIFF_CONFIG', 'watchdiff.config.json')) as $watcher) {
            $watchDiff->watch($watcher);
        }

        $port   = EnvOption::int($input, 'port', 'WATCHDIFF_PORT', 9090);
        $host   = EnvOption::stringOr($input, 'host', 'WATCHDIFF_HOST', '127.0.0.1');
        $socket = stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
        if (false === $socket) {
            $output->writeln(sprintf('<error>Unable to bind %s:%d: %s</error>', $host, $port, $errstr));

            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Listening on http://%s:%d (Ctrl+C to stop)</info>', $host, $port));
        if ('127.0.0.1' !== $host && 'localhost' !== $host) {
            $output->writeln('<comment>/status and /metrics are unauthenticated and may expose monitored URLs (including any secrets embedded in them) to anyone who can reach this port.</comment>');
        }

        while (true) {
            $connection = @stream_socket_accept($socket, -1);
            if (false === $connection) {
                continue;
            }

            // A client that never finishes sending its request line would
            // otherwise hang this single-threaded accept loop indefinitely.
            stream_set_timeout($connection, 5);
            $this->handleConnection($connection, $watchDiff);
        }
    }

    /** @param resource $connection */
    private function handleConnection($connection, \WatchDiff\Core\WatchDiff $watchDiff): void
    {
        $request = fgets($connection, 8192);
        $path    = \is_string($request) && 1 === preg_match('#^[A-Z]+\s(/\S*)#', $request, $m) ? $m[1] : '/';

        [$status, $body, $contentType] = match (true) {
            '/health' === $path  => [200, json_encode(['status' => 'ok']) ?: '{}', 'application/json'],
            '/status' === $path  => [200, $this->statusJson($watchDiff), 'application/json'],
            '/metrics' === $path => [200, $this->metrics($watchDiff), 'text/plain'],
            default              => [404, json_encode(['error' => 'not found']) ?: '{}', 'application/json'],
        };

        fwrite($connection, "HTTP/1.1 {$status} OK\r\nContent-Type: {$contentType}\r\nContent-Length: ".\strlen($body)."\r\nConnection: close\r\n\r\n{$body}");
        fclose($connection);
    }

    private function statusJson(\WatchDiff\Core\WatchDiff $watchDiff): string
    {
        $statuses = array_map(static fn (WatcherStatus $s) => [
            'url'          => $s->url,
            'label'        => $s->label,
            'paused'       => $s->paused,
            'checksCount'  => $s->checksCount,
            'changesCount' => $s->changesCount,
            'errorsCount'  => $s->errorsCount,
        ], $watchDiff->status());

        return json_encode($statuses) ?: '[]';
    }

    private function metrics(\WatchDiff\Core\WatchDiff $watchDiff): string
    {
        $lines = [];
        foreach ($watchDiff->status() as $status) {
            $label   = addslashes($status->label);
            $lines[] = "watchdiff_checks_total{watcher=\"{$label}\"} {$status->checksCount}";
            $lines[] = "watchdiff_changes_total{watcher=\"{$label}\"} {$status->changesCount}";
            $lines[] = "watchdiff_errors_total{watcher=\"{$label}\"} {$status->errorsCount}";
        }

        return implode("\n", $lines)."\n";
    }
}
