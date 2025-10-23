<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class AdminAuditLogRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function record(int $actorId, string $action, string $target, array $detail): void
    {
        $sql = <<<'SQL'
            INSERT INTO admin_audit_logs (
                actor_id, action, target, detail_json, created_at
            ) VALUES (
                :actor_id, :action, :target, :detail_json, :created_at
            )
        SQL;

        $stmt = $this->pdo->prepare($sql);

        $params = [
            ':actor_id' => $actorId,
            ':action' => $action,
            ':target' => $target,
            ':detail_json' => json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':created_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        ];

        if (!$stmt->execute($params)) {
            throw new RuntimeException('Failed to write admin audit log.');
        }
    }
}
