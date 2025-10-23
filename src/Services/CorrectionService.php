<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AdminAuditLogRepository;
use App\Repositories\CorrectionRequestRepository;
use App\Repositories\DailySummaryRepository;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class CorrectionService
{
    public function __construct(
        private CorrectionRequestRepository $requests,
        private DailySummaryRepository $dailySummaries,
        private AdminAuditLogRepository $auditLogs
    ) {
    }

    public function submitRequest(int $userId, string $workDate, array $after, string $reason, ?array $before = null): int
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        if ($before === null) {
            $before = $this->dailySummaries->findByUserAndDate($userId, $workDate) ?? [];
        }

        $payload = [
            ':user_id' => $userId,
            ':work_date' => $workDate,
            ':before_json' => json_encode($before ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':after_json' => json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':reason_text' => $reason,
            ':status' => 'pending',
            ':approver_id' => null,
            ':decided_at' => null,
            ':created_at' => $now->format('Y-m-d H:i:s'),
            ':updated_at' => $now->format('Y-m-d H:i:s'),
        ];

        return $this->requests->create($payload);
    }

    public function listPending(): array
    {
        return $this->requests->listPending();
    }

    public function listUserRequests(int $userId, string $yearMonth): array
    {
        return $this->requests->listByUserAndMonth($userId, $yearMonth);
    }

    public function decide(int $requestId, int $approverId, string $decision): void
    {
        $request = $this->requests->findById($requestId);

        if (!$request) {
            throw new RuntimeException('Correction request not found.');
        }

        if ($request['status'] !== 'pending') {
            throw new RuntimeException('Correction request already processed.');
        }

        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new RuntimeException('Invalid correction decision.');
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $this->requests->updateStatus($requestId, [
            'status' => $decision,
            'approver_id' => $approverId,
            'decided_at' => $now->format('Y-m-d H:i:s'),
        ]);

        if ($decision === 'approved') {
            $after = json_decode($request['after_json'], true) ?: [];
            $this->applySummaryOverride(
                (int) $request['user_id'],
                $request['work_date'],
                $after,
                $requestId,
                $approverId
            );
        }

        $this->auditLogs->record(
            $approverId,
            'correction_' . $decision,
            'correction_requests#' . $requestId,
            [
                'decision' => $decision,
                'request' => $request,
            ]
        );
    }

    private function applySummaryOverride(int $userId, string $workDate, array $after, int $requestId, int $approverId): void
    {
        $clockIn = $after['clock_in_at'] ?? null;
        $clockOut = $after['clock_out_at'] ?? null;
        $breakMinutes = (int) ($after['break_minutes'] ?? 0);
        $totalMinutes = $this->calculateTotalMinutes($clockIn, $clockOut, $breakMinutes);

        $snapshotHash = hash(
            'sha256',
            json_encode([
                'request_id' => $requestId,
                'approver_id' => $approverId,
                'after' => $after,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $this->dailySummaries->upsert($userId, $workDate, [
            'clock_in_at' => $clockIn,
            'clock_out_at' => $clockOut,
            'break_minutes' => $breakMinutes,
            'total_work_minutes' => $totalMinutes,
            'snapshot_hash' => $snapshotHash,
        ]);
    }

    private function calculateTotalMinutes(?string $clockIn, ?string $clockOut, int $breakMinutes): ?int
    {
        if (!$clockIn || !$clockOut) {
            return null;
        }

        $in = new DateTimeImmutable($clockIn, new DateTimeZone('UTC'));
        $out = new DateTimeImmutable($clockOut, new DateTimeZone('UTC'));

        if ($out <= $in) {
            return null;
        }

        $diff = $in->diff($out);

        $minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;

        return max(0, $minutes - $breakMinutes);
    }
}
