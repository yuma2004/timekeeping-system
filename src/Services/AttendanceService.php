<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AttendanceRepository;
use App\Support\Config;
use App\Security\EventSigner;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class AttendanceService
{
    private const VALID_KINDS = ['in', 'out', 'break_start', 'break_end'];

    public function __construct(
        private AttendanceRepository $attendance,
        private DailySummaryService $dailySummaries,
        private EventSigner $signer
    ) {
    }

    public function recordPunch(int $userId, string $kind, array $context = []): array
    {
        if (!in_array($kind, self::VALID_KINDS, true)) {
            throw new RuntimeException('Invalid punch kind supplied.');
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $occurredAt = $now->format('Y-m-d H:i:s');

        $prev = $this->attendance->findLastEventForUser($userId);
        $prevHmac = $prev['hmac_link'] ?? '';

        $eventId = $this->signer->generateEventId();

        $attributes = [
            'event_id' => $eventId,
            'user_id' => $userId,
            'kind' => $kind,
            'occurred_at' => $occurredAt,
        ];

        $hmac = $this->signer->sign($attributes, (string) $prevHmac);

        $payload = [
            'event_id' => $eventId,
            'user_id' => $userId,
            'kind' => $kind,
            'occurred_at' => $occurredAt,
            'ip' => $context['ip'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
            'raw_payload' => json_encode($context['payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'prev_hmac' => $prevHmac ?: null,
            'hmac_link' => $hmac,
            'created_at' => $now->format('Y-m-d H:i:s'),
        ];

        $this->attendance->insertEvent($payload);

        $localZone = new DateTimeZone(Config::get('app.timezone', 'Asia/Tokyo'));
        $workDate = $now->setTimezone($localZone)->format('Y-m-d');
        $summary = $this->dailySummaries->recalculateForDate($userId, $workDate);

        return [
            'event' => $payload,
            'summary' => $summary,
        ];
    }

    public function getMonthlySummaries(int $userId, string $yearMonth): array
    {
        return $this->dailySummaries->getMonthlySummaries($userId, $yearMonth);
    }
}
