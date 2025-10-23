<?php

declare(strict_types=1);

namespace App;

use App\Http\Router;
use App\Http\Response;
use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\PunchController;
use App\Repositories\AdminAuditLogRepository;
use App\Repositories\AttendanceRepository;
use App\Repositories\CorrectionRequestRepository;
use App\Repositories\DailySummaryRepository;
use App\Repositories\EmployeeRepository;
use App\Security\EventSigner;
use App\Services\AttendanceService;
use App\Services\AuthService;
use App\Services\CorrectionService;
use App\Services\DailySummaryService;
use App\Services\ExportService;
use App\Support\Database;
use App\Support\Flash;
use PDO;

final class Application
{
    private Router $router;
    private ?PDO $pdo = null;

    /** @var array<string, mixed> */
    private array $instances = [];

    public function __construct(?Router $router = null)
    {
        $this->router = $router ?? new Router();
        Flash::init();
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function registerRoutes(): void
    {
        $auth = $this->authController();
        $punch = $this->punchController();
        $admin = $this->adminController();

        $this->router->get('/login', [$auth, 'showLogin']);
        $this->router->post('/login', [$auth, 'login']);
        $this->router->post('/logout', [$auth, 'logout']);

        $this->router->get('/', [$punch, 'dashboard']);
        $this->router->post('/punch', [$punch, 'punch']);
        $this->router->get('/me/days', [$punch, 'daysJson']);
        $this->router->post('/me/corrections', [$punch, 'submitCorrection']);

        $this->router->get('/admin', [$admin, 'dashboard']);
        $this->router->get('/admin/corrections', [$admin, 'corrections']);
        $this->router->post('/admin/corrections/{id}/approve', [$admin, 'approveCorrection']);
        $this->router->post('/admin/corrections/{id}/reject', [$admin, 'rejectCorrection']);
        $this->router->get('/admin/users', [$admin, 'users']);
        $this->router->post('/admin/users', [$admin, 'createUser']);
        $this->router->get('/admin/export', [$admin, 'export']);

        $this->router->get('/healthz', static fn() => Response::text('ok'));
    }

    /**
     * @template T
     * @param class-string<T> $key
     * @param callable():T $factory
     * @return T
     */
    private function singleton(string $key, callable $factory): mixed
    {
        if (!array_key_exists($key, $this->instances)) {
            $this->instances[$key] = $factory();
        }

        return $this->instances[$key];
    }

    private function authController(): AuthController
    {
        return $this->singleton(AuthController::class, function () {
            return new AuthController($this->authService());
        });
    }

    private function punchController(): PunchController
    {
        return $this->singleton(PunchController::class, function () {
            return new PunchController(
                $this->authService(),
                $this->attendanceService(),
                $this->correctionService(),
                $this->dailySummaryRepository(),
                $this->attendanceRepository()
            );
        });
    }

    private function adminController(): AdminController
    {
        return $this->singleton(AdminController::class, function () {
            return new AdminController(
                $this->authService(),
                $this->correctionService(),
                $this->employeeRepository(),
                $this->dailySummaryRepository(),
                $this->exportService()
            );
        });
    }

    private function authService(): AuthService
    {
        return $this->singleton(AuthService::class, function () {
            return new AuthService($this->employeeRepository());
        });
    }

    private function attendanceService(): AttendanceService
    {
        return $this->singleton(AttendanceService::class, function () {
            return new AttendanceService(
                $this->attendanceRepository(),
                $this->dailySummaryService(),
                $this->eventSigner()
            );
        });
    }

    private function dailySummaryService(): DailySummaryService
    {
        return $this->singleton(DailySummaryService::class, function () {
            return new DailySummaryService(
                $this->dailySummaryRepository(),
                $this->attendanceRepository()
            );
        });
    }

    private function correctionService(): CorrectionService
    {
        return $this->singleton(CorrectionService::class, function () {
            return new CorrectionService(
                $this->correctionRepository(),
                $this->dailySummaryRepository(),
                $this->auditLogRepository()
            );
        });
    }

    private function exportService(): ExportService
    {
        return $this->singleton(ExportService::class, function () {
            return new ExportService(
                $this->employeeRepository(),
                $this->dailySummaryRepository(),
                $this->correctionRepository()
            );
        });
    }

    private function employeeRepository(): EmployeeRepository
    {
        return $this->singleton(EmployeeRepository::class, fn() => new EmployeeRepository($this->pdo()));
    }

    private function attendanceRepository(): AttendanceRepository
    {
        return $this->singleton(AttendanceRepository::class, fn() => new AttendanceRepository($this->pdo()));
    }

    private function dailySummaryRepository(): DailySummaryRepository
    {
        return $this->singleton(DailySummaryRepository::class, fn() => new DailySummaryRepository($this->pdo()));
    }

    private function correctionRepository(): CorrectionRequestRepository
    {
        return $this->singleton(CorrectionRequestRepository::class, fn() => new CorrectionRequestRepository($this->pdo()));
    }

    private function auditLogRepository(): AdminAuditLogRepository
    {
        return $this->singleton(AdminAuditLogRepository::class, fn() => new AdminAuditLogRepository($this->pdo()));
    }

    private function eventSigner(): EventSigner
    {
        return $this->singleton(EventSigner::class, fn() => new EventSigner());
    }

    private function pdo(): PDO
    {
        if (!$this->pdo instanceof PDO) {
            $this->pdo = Database::connection();
        }

        return $this->pdo;
    }
}
