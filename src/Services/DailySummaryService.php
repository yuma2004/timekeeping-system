<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AttendanceRepository;
use App\Repositories\DailySummaryRepository;
use App\Support\Config;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final class DailySummaryService
{
    public function __construct(
        private DailySummaryRepository $dailySummaries,
        private AttendanceRepository $attendance
    ) {
    }

    public function recalculateForDate(int $userId, string $workDate): array
    {
        $localZone = new DateTimeZone(Config::get('app.timezone', 'Asia/Tokyo'));
        $startLocal = new DateTimeImmutable($workDate . ' 00:00:00', $localZone);
        $endLocal = $startLocal->modify('+1 day');

        $startUtc = $startLocal->setTimezone(new DateTimeZone('UTC'));
        $endUtc = $endLocal->setTimezone(new DateTimeZone('UTC'))->modify('-1 second');

        $events = $this->attendance->findEventsBetween($userId, $startUtc, $endUtc);

        $clockIn = null;
        $clockOut = null;
        $breakMinutes = 0;
        $openBreak = null;
        $timezone = new DateTimeZone('UTC');

        foreach ($events as $event) {
            $occurredAt = new DateTimeImmutable($event['occurred_at'], $timezone);
            $kind = $event['kind'];

            if ($kind === 'in' && $clockIn === null) {
                $clockIn = $occurredAt;
            }

            if ($kind === 'out') {
                $clockOut = $occurredAt;
            }

            if ($kind === 'break_start') {
                $openBreak = $occurredAt;
            }

            if ($kind === 'break_end' && $openBreak) {
                $interval = $openBreak->diff($occurredAt);
                $breakMinutes += $this->intervalToMinutes($interval);
                $openBreak = null;
            }
        }

        $totalWorkMinutes = null;

        if ($clockIn && $clockOut) {
            $interval = $clockIn->diff($clockOut);
            $totalWorkMinutes = max(0, $this->intervalToMinutes($interval) - $breakMinutes);
        }

        $snapshotHash = $events ? $this->snapshotHash($events) : null;

        $payload = [
            'clock_in_at' => $clockIn?->format('Y-m-d H:i:s'),
            'clock_out_at' => $clockOut?->format('Y-m-d H:i:s'),
            'break_minutes' => $breakMinutes,
            'total_work_minutes' => $totalWorkMinutes,
            'snapshot_hash' => $snapshotHash,
        ];

        $this->dailySummaries->upsert($userId, $workDate, $payload);

        return array_merge($payload, ['events' => $events]);
    }

    public function getMonthlySummaries(int $userId, string $yearMonth): array
    {
        return $this->dailySummaries->listByMonth($userId, $yearMonth);
    }

    private function snapshotHash(array $events): string
    {
        $pieces = array_map(
            static fn(array $event): string => implode('|', [
                $event['event_id'],
                $event['kind'],
                $event['occurred_at'],
                $event['hmac_link'],
            ]),
            $events
        );

        return hash('sha256', implode(';', $pieces));
    }

    private function intervalToMinutes(DateInterval $interval): int
    {
        return ($interval->days * 24 * 60)
            + ($interval->h * 60)
            + $interval->i
            + (int) round($interval->s / 60);
    }
}
