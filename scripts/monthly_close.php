<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Repositories\CorrectionRequestRepository;
use App\Repositories\DailySummaryRepository;
use App\Repositories\EmployeeRepository;
use App\Services\ExportService;
use App\Support\Database;
use DateTimeImmutable;

$pdo = Database::connection();
$employees = new EmployeeRepository($pdo);
$daily = new DailySummaryRepository($pdo);
$corrections = new CorrectionRequestRepository($pdo);
$exporter = new ExportService($employees, $daily, $corrections);

$targetMonth = (new DateTimeImmutable('first day of last month'))->format('Y-m');

echo "[monthly_close] generating export for {$targetMonth}\n";

$csv = $exporter->generateMonthlyCsv($targetMonth, false);
$path = __DIR__ . '/../storage/exports/attendance_' . str_replace('-', '', $targetMonth) . '.csv';

file_put_contents($path, $csv);

echo "[monthly_close] saved to {$path}\n";
echo "必要に応じてWINGのバックアップと併せて世代管理してください。\n";
