<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class CorrectionRequestRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(array $data): int
    {
        $sql = <<<'SQL'
            INSERT INTO correction_requests (
                user_id, work_date, before_json, after_json,
                reason_text, status, approver_id, decided_at,
                created_at, updated_at
            ) VALUES (
                :user_id, :work_date, :before_json, :after_json,
                :reason_text, :status, :approver_id, :decided_at,
                :created_at, :updated_at
            )
        SQL;

        $stmt = $this->pdo->prepare($sql);

        if (!$stmt->execute($data)) {
            throw new RuntimeException('Failed to create correction request.');
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM correction_requests WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();

        return $result !== false ? $result : null;
    }

    public function listByUserAndMonth(int $userId, string $yearMonth): array
    {
        $sql = <<<'SQL'
            SELECT *
            FROM correction_requests
            WHERE user_id = :user_id
              AND DATE_FORMAT(work_date, '%Y-%m') = :year_month
            ORDER BY created_at DESC
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':year_month' => $yearMonth,
        ]);

        return $stmt->fetchAll();
    }

    public function listApprovedByUserAndMonth(int $userId, string $yearMonth): array
    {
        $sql = <<<'SQL'
            SELECT *
            FROM correction_requests
            WHERE user_id = :user_id
              AND status = 'approved'
              AND DATE_FORMAT(work_date, '%Y-%m') = :year_month
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':year_month' => $yearMonth,
        ]);

        return $stmt->fetchAll();
    }

    public function listPending(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM correction_requests WHERE status = \'pending\' ORDER BY created_at ASC');
        return $stmt->fetchAll();
    }

    public function updateStatus(int $id, array $values): void
    {
        $fields = [];
        $params = [':id' => $id];

        foreach ($values as $column => $value) {
            $fields[] = "{$column} = :{$column}";
            $params[":{$column}"] = $value;
        }

        if (!$fields) {
            return;
        }

        $params[':updated_at'] = $values['updated_at'] ?? (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $fields[] = 'updated_at = :updated_at';

        $sql = 'UPDATE correction_requests SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);

        if (!$stmt->execute($params)) {
            throw new RuntimeException('Failed to update correction request.');
        }
    }
}
