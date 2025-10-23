<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CorrectionRequestRepository;
use App\Repositories\DailySummaryRepository;
use App\Repositories\EmployeeRepository;
use DateTimeImmutable;
use RuntimeException;

final class ExportService
{
    public function __construct(
        private EmployeeRepository $employees,
        private DailySummaryRepository $dailySummaries,
        private CorrectionRequestRepository $corrections
    ) {
    }

    public function generateMonthlyCsv(string $yearMonth, bool $withBom = false): string
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            throw new RuntimeException('Invalid month format. Expected YYYY-MM.');
        }

        $employees = $this->employees->listAll();

        $stream = fopen('php://temp', 'r+');

        if ($withBom) {
            fwrite($stream, "\xEF\xBB\xBF");
        }

        $header = [
            'employee_code',
            'display_name',
            'role',
            'work_date',
            'clock_in_at',
            'clock_out_at',
            'break_minutes',
            'total_work_minutes',
            'total_work_hours',
            'correction_flag',
        ];

        fputcsv($stream, $header);

        foreach ($employees as $employee) {
            $userId = (int) $employee['id'];
            $summaries = $this->dailySummaries->listByMonth($userId, $yearMonth);
            $corrections = $this->corrections->listApprovedByUserAndMonth($userId, $yearMonth);
            $correctionMap = [];

            foreach ($corrections as $correction) {
                $correctionMap[$correction['work_date']] = true;
            }

            foreach ($summaries as $summary) {
                $workDate = $summary['work_date'];

                fputcsv($stream, [
                    $employee['employee_code'],
                    $employee['display_name'],
                    $employee['role'],
                    $workDate,
                    $summary['clock_in_at'] ?? '',
                    $summary['clock_out_at'] ?? '',
                    (string) $summary['break_minutes'],
                    (string) ($summary['total_work_minutes'] ?? ''),
                    $this->formatHours((int) ($summary['total_work_minutes'] ?? 0)),
                    isset($correctionMap[$workDate]) ? '1' : '0',
                ]);
            }
        }

        rewind($stream);
        $csv = stream_get_contents($stream);

        if ($csv === false) {
            throw new RuntimeException('Failed to generate CSV export.');
        }

        return $csv;
    }

    private function formatHours(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0:00';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return sprintf('%d:%02d', $hours, $mins);
    }
}
