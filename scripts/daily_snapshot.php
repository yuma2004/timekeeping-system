<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Repositories\AttendanceRepository;
use App\Repositories\DailySummaryRepository;
use App\Repositories\EmployeeRepository;
use App\Services\DailySummaryService;
use App\Support\Config;
use App\Support\Database;
use DateTimeImmutable;
use DateTimeZone;

$pdo = Database::connection();
$employees = new EmployeeRepository($pdo);
$attendance = new AttendanceRepository($pdo);
$daily = new DailySummaryRepository($pdo);
$service = new DailySummaryService($daily, $attendance);

$tz = new DateTimeZone(Config::get('app.timezone', 'Asia/Tokyo'));
$targetDate = (new DateTimeImmutable('yesterday', $tz))->format('Y-m-d');

echo "[daily_snapshot] target={$targetDate}\n";

foreach ($employees->listActive() as $employee) {
    $summary = $service->recalculateForDate((int) $employee['id'], $targetDate);
    $hash = $summary['snapshot_hash'] ?? 'n/a';
    echo sprintf(
        " - %s (%s): in=%s out=%s work=%s snapshot=%s\n",
        $employee['display_name'],
        $employee['employee_code'],
        $summary['clock_in_at'] ?? '-',
        $summary['clock_out_at'] ?? '-',
        $summary['total_work_minutes'] ?? '-',
        $hash
    );
}

echo "[daily_snapshot] completed\n";
