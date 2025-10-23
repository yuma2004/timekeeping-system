<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\EmployeeRepository;
use App\Support\Config;
use DateTimeImmutable;
use RuntimeException;

final class AuthService
{
    private ?array $cachedUser = null;

    public function __construct(private EmployeeRepository $employees)
    {
    }

    public function attemptLogin(string $loginId, string $password): bool
    {
        $employee = $this->employees->findByLoginId($loginId);

        if (!$employee) {
            return false;
        }

        $hash = $employee['password_hash'] ?? '';

        if (!password_verify($password, $hash)) {
            return false;
        }

        if (password_needs_rehash($hash, Config::get('security.password_algo', PASSWORD_DEFAULT))) {
            $newHash = password_hash($password, Config::get('security.password_algo', PASSWORD_DEFAULT));
            $this->employees->update((int) $employee['id'], [
                'password_hash' => $newHash,
            ]);
        }

        $_SESSION['uid'] = (int) $employee['id'];
        $_SESSION['last_login_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $_SESSION['roles_guard_token'] = bin2hex(random_bytes(16));
        session_regenerate_id(true);

        $this->cachedUser = $employee;

        return true;
    }

    public function logout(): void
    {
        $this->cachedUser = null;
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function check(): bool
    {
        return isset($_SESSION['uid']) && $this->user() !== null;
    }

    public function user(): ?array
    {
        if ($this->cachedUser !== null) {
            return $this->cachedUser;
        }

        if (!isset($_SESSION['uid'])) {
            return null;
        }

        $user = $this->employees->findById((int) $_SESSION['uid']);

        if (!$user || !(int) $user['active']) {
            $this->logout();
            return null;
        }

        $this->cachedUser = $user;

        return $user;
    }

    public function requireLogin(): array
    {
        $user = $this->user();

        if (!$user) {
            throw new RuntimeException('Authentication required.');
        }

        return $user;
    }

    public function requireRole(array $roles): array
    {
        $user = $this->requireLogin();

        if (!in_array($user['role'], $roles, true)) {
            throw new RuntimeException('Insufficient permission.');
        }

        return $user;
    }
}
