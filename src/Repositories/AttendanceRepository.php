<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Config;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
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
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $yearMonth)) {
            throw new InvalidArgumentException(
                sprintf('Invalid yearMonth value "%s". Expected format is YYYY-MM.', $yearMonth)
            );
        }

        $localZone = new DateTimeZone(Config::get('app.timezone', 'Asia/Tokyo'));
        $startLocal = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $yearMonth . '-01 00:00:00', $localZone);
        if ($startLocal === false) {
            throw new InvalidArgumentException(
                sprintf('Failed to build start date for "%s" in timezone "%s".', $yearMonth, $localZone->getName())
            );
        }

        $endLocal = $startLocal->modify('first day of next month');

        $utcZone = new DateTimeZone('UTC');
        $startUtc = $startLocal->setTimezone($utcZone);
        $endUtc = $endLocal->setTimezone($utcZone)->modify('-1 second');

        return $this->findEventsBetween($userId, $startUtc, $endUtc);
    }
}
