<?php

declare(strict_types=1);

namespace WatchDiff\Core\Export;

use WatchDiff\Core\Model\DiffReport;
use WatchDiff\Core\Model\Snapshot;

/**
 * Optional XLSX export. Requires phpoffice/phpspreadsheet, which is not a
 * hard dependency of watchdiff-core - install it yourself if you need this:
 *
 *     composer require phpoffice/phpspreadsheet
 */
final class XlsxExporter
{
    public function __construct()
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            throw new \RuntimeException(
                'XlsxExporter requires phpoffice/phpspreadsheet. Install it with: composer require phpoffice/phpspreadsheet'
            );
        }
    }

    /** @param DiffReport[] $reports */
    public function exportReports(array $reports): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['comparedAt', 'kind', 'before', 'after', 'context'], null, 'A1');

        $row = 2;
        foreach ($reports as $report) {
            foreach ($report->changes as $change) {
                $sheet->fromArray([
                    $report->comparedAt->format(DATE_ATOM),
                    $change->kind->value,
                    $change->before,
                    $change->after,
                    $change->context,
                ], null, "A{$row}");
                ++$row;
            }
        }

        return $this->write($spreadsheet);
    }

    /** @param Snapshot[] $snapshots */
    public function exportSnapshots(array $snapshots): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['capturedAt', 'statusCode', 'responseTimeMs', 'content'], null, 'A1');

        $row = 2;
        foreach ($snapshots as $snapshot) {
            $sheet->fromArray([
                $snapshot->capturedAt->format(DATE_ATOM),
                $snapshot->statusCode,
                $snapshot->responseTimeMs,
                $snapshot->content,
            ], null, "A{$row}");
            ++$row;
        }

        return $this->write($spreadsheet);
    }

    private function write(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'watchdiff-xlsx-');
        if (false === $tmpFile) {
            throw new \RuntimeException('Unable to create a temporary file for the XLSX export.');
        }

        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($tmpFile);
        $contents = file_get_contents($tmpFile);
        unlink($tmpFile);

        return false === $contents ? '' : $contents;
    }
}
