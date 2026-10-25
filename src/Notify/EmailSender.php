<?php

declare(strict_types=1);

namespace WatchDiff\Core\Notify;

use WatchDiff\Core\Model\DiffReport;

/**
 * Optional email alerts. Requires symfony/mailer, which is not a hard
 * dependency of watchdiff-core - install it yourself if you need this:
 *
 *     composer require symfony/mailer
 */
final class EmailSender
{
    public function __construct(private readonly string $dsn)
    {
        if (!class_exists(\Symfony\Component\Mailer\Mailer::class)) {
            throw new \RuntimeException(
                'EmailSender requires symfony/mailer. Install it with: composer require symfony/mailer'
            );
        }
    }

    public function send(string $to, string $from, DiffReport $report): void
    {
        $transport = \Symfony\Component\Mailer\Transport::fromDsn($this->dsn);
        $mailer    = new \Symfony\Component\Mailer\Mailer($transport);

        $email = (new \Symfony\Component\Mime\Email())
            ->from($from)
            ->to($to)
            ->subject(sprintf('[watchdiff] %s changed', $report->label))
            ->text($this->body($report));

        $mailer->send($email);
    }

    private function body(DiffReport $report): string
    {
        $lines = [sprintf('%s: %d change(s) detected', $report->label, \count($report->changes)), ''];

        foreach ($report->changes as $change) {
            $lines[] = sprintf('[%s] %s -> %s', $change->kind->value, $change->before ?? '-', $change->after ?? '-');
        }

        return implode("\n", $lines);
    }
}
