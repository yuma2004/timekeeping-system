<?php

declare(strict_types=1);

namespace App\Repositories;

use DateTimeInterface;
use PDO;
use RuntimeException;

final class AttendanceRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findLastEventForUser(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM attendance_events WHERE user_id = :user_id ORDER BY occurred_at DESC, id DESC LIMIT 1');
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch();

        return $result !== false ? $result : null;
    }

    public function insertEvent(array $payload): void
    {
        $sql = <<<'SQL'
            INSERT INTO attendance_events (
                event_id, user_id, kind, occurred_at, ip, user_agent, raw_payload,
                prev_hmac, hmac_link, created_at
            ) VALUES (
                :event_id, :user_id, :kind, :occurred_at, :ip, :user_agent, :raw_payload,
                :prev_hmac, :hmac_link, :created_at
            )
        SQL;

        $stmt = $this->pdo->prepare($sql);

        if (!$stmt->execute($payload)) {
            throw new RuntimeException('Failed to insert attendance event.');
        }
    }

    public function findEventsForDate(int $userId, string $date): array
    {
        $sql = <<<'SQL'
            SELECT * FROM attendance_events
            WHERE user_id = :user_id
              AND DATE(occurred_at) = :work_date
            ORDER BY occurred_at ASC, id ASC
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':work_date' => $date,
        ]);

        return $stmt->fetchAll();
    }

    public function findEventsBetween(int $userId, DateTimeInterface $start, DateTimeInterface $end): array
    {
        $sql = <<<'SQL'
            SELECT * FROM attendance_events
            WHERE user_id = :user_id
              AND occurred_at BETWEEN :start AND :end
            ORDER BY occurred_at ASC, id ASC
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':start' => $start->format('Y-m-d H:i:s'),
            ':end' => $end->format('Y-m-d H:i:s'),
        ]);

        return $stmt->fetchAll();
    }

    public function findEventsForMonth(int $userId, string $yearMonth): array
    {
        $start = new \DateTimeImmutable("{$yearMonth}-01 00:00:00", new \DateTimeZone('UTC'));
        $end = $start->modify('first day of next month')->modify('-1 second');

        return $this->findEventsBetween($userId, $start, $end);
    }
}
