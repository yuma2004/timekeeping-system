<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOException;
use RuntimeException;

final class EmployeeRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM employees WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();

        return $result !== false ? $result : null;
    }

    public function findByLoginId(string $loginId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM employees WHERE login_id = :login_id AND active = 1 LIMIT 1');
        $stmt->execute([':login_id' => $loginId]);
        $result = $stmt->fetch();

        return $result !== false ? $result : null;
    }

    public function listAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM employees ORDER BY active DESC, display_name ASC');
        return $stmt->fetchAll();
    }

    public function listActive(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM employees WHERE active = 1 ORDER BY display_name ASC');
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $sql = <<<'SQL'
            INSERT INTO employees (employee_code, login_id, password_hash, display_name, role, active, created_at, updated_at)
            VALUES (:employee_code, :login_id, :password_hash, :display_name, :role, :active, :created_at, :updated_at)
        SQL;

        $stmt = $this->pdo->prepare($sql);

        $now = $data['now'] ?? (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $params = [
            ':employee_code' => $data['employee_code'],
            ':login_id' => $data['login_id'],
            ':password_hash' => $data['password_hash'],
            ':display_name' => $data['display_name'],
            ':role' => $data['role'] ?? 'employee',
            ':active' => $data['active'] ?? 1,
            ':created_at' => $now,
            ':updated_at' => $now,
        ];

        if (!$stmt->execute($params)) {
            throw new RuntimeException('Failed to create employee record.');
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [];
        $params = [':id' => $id];

        foreach ($data as $column => $value) {
            $fields[] = "{$column} = :{$column}";
            $params[":{$column}"] = $value;
        }

        $fields[] = 'updated_at = :updated_at';
        $params[':updated_at'] = $data['updated_at'] ?? (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $sql = 'UPDATE employees SET ' . implode(', ', $fields) . ' WHERE id = :id LIMIT 1';

        $stmt = $this->pdo->prepare($sql);

        if (!$stmt->execute($params)) {
            throw new RuntimeException('Failed to update employee record.');
        }
    }

    public function deactivate(int $id): void
    {
        $this->update($id, ['active' => 0]);
    }
}
