<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Repositories\DailySummaryRepository;
use App\Repositories\EmployeeRepository;
use App\Support\Config;
use App\Support\Database;
use DateTimeImmutable;
use DateTimeZone;

$pdo = Database::connection();
$employees = new EmployeeRepository($pdo);
$daily = new DailySummaryRepository($pdo);

$tz = new DateTimeZone(Config::get('app.timezone', 'Asia/Tokyo'));
$targetDate = (new DateTimeImmutable('yesterday', $tz))->format('Y-m-d');

echo "[missing_checkout_alert] target={$targetDate}\n";

$alerts = [];

foreach ($employees->listActive() as $employee) {
    $summary = $daily->findByUserAndDate((int) $employee['id'], $targetDate);

    if ($summary && $summary['clock_in_at'] && empty($summary['clock_out_at'])) {
        $alerts[] = [
            'employee' => $employee,
            'summary' => $summary,
        ];
    }
}

if (!$alerts) {
    echo "すべて退勤済みです。\n";
    exit(0);
}

foreach ($alerts as $alert) {
    $employee = $alert['employee'];
    $summary = $alert['summary'];
    echo sprintf(
        " - %s (%s): clock_in=%s, clock_out=未登録\n",
        $employee['display_name'],
        $employee['employee_code'],
        $summary['clock_in_at'] ?? '-'
    );
}

echo "WINGの通知機能やSlack Webhookと連携させることで自動通知が可能です。\n";
