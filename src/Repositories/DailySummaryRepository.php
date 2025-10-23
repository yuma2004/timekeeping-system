<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class DailySummaryRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByUserAndDate(int $userId, string $date): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM daily_summaries WHERE user_id = :user_id AND work_date = :work_date LIMIT 1');
        $stmt->execute([
            ':user_id' => $userId,
            ':work_date' => $date,
        ]);

        $result = $stmt->fetch();

        return $result !== false ? $result : null;
    }

    public function upsert(int $userId, string $date, array $values): void
    {
        $sql = <<<'SQL'
            INSERT INTO daily_summaries (
                user_id, work_date, clock_in_at, clock_out_at,
                break_minutes, total_work_minutes, snapshot_hash
            ) VALUES (
                :user_id, :work_date, :clock_in_at, :clock_out_at,
                :break_minutes, :total_work_minutes, :snapshot_hash
            )
            ON DUPLICATE KEY UPDATE
                clock_in_at = VALUES(clock_in_at),
                clock_out_at = VALUES(clock_out_at),
                break_minutes = VALUES(break_minutes),
                total_work_minutes = VALUES(total_work_minutes),
                snapshot_hash = VALUES(snapshot_hash)
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $payload = [
            ':user_id' => $userId,
            ':work_date' => $date,
            ':clock_in_at' => $values['clock_in_at'],
            ':clock_out_at' => $values['clock_out_at'],
            ':break_minutes' => $values['break_minutes'],
            ':total_work_minutes' => $values['total_work_minutes'],
            ':snapshot_hash' => $values['snapshot_hash'] ?? null,
        ];

        if (!$stmt->execute($payload)) {
            throw new RuntimeException('Failed to upsert daily summary.');
        }
    }

    public function listByMonth(int $userId, string $yearMonth): array
    {
        $sql = <<<'SQL'
            SELECT *
            FROM daily_summaries
            WHERE user_id = :user_id
              AND DATE_FORMAT(work_date, '%Y-%m') = :year_month
            ORDER BY work_date ASC
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':year_month' => $yearMonth,
        ]);

        return $stmt->fetchAll();
    }

    public function setSnapshotHash(int $userId, string $date, string $hash): void
    {
        $sql = 'UPDATE daily_summaries SET snapshot_hash = :hash WHERE user_id = :user_id AND work_date = :work_date';
        $stmt = $this->pdo->prepare($sql);

        if (!$stmt->execute([
            ':hash' => $hash,
            ':user_id' => $userId,
            ':work_date' => $date,
        ])) {
            throw new RuntimeException('Failed to update snapshot hash.');
        }
    }
}
