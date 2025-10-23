<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\DailySummaryRepository;
use App\Repositories\EmployeeRepository;
use App\Security\CsrfTokenManager;
use App\Services\AuthService;
use App\Services\CorrectionService;
use App\Services\ExportService;
use App\Support\Config;
use App\Support\Flash;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class AdminController
{
    public function __construct(
        private AuthService $auth,
        private CorrectionService $corrections,
        private EmployeeRepository $employees,
        private DailySummaryRepository $dailySummaries,
        private ExportService $exportService
    ) {
    }

    public function dashboard(Request $request): void
    {
        $user = $this->auth->requireRole(['approver', 'admin']);
        $today = $this->today();
        $pending = $this->corrections->listPending();
        $missing = $this->findMissingClockOuts($today);

        Response::view('admin/dashboard', [
            'user' => $user,
            'today' => $today,
            'pending_requests' => $pending,
            'missing_clockouts' => $missing,
            'flash' => Flash::all(),
            'csrf_tokens' => [
                'logout' => CsrfTokenManager::generateToken('logout_form'),
                'correction_decision' => CsrfTokenManager::generateToken('admin_correction'),
            ],
        ]);
    }

    public function corrections(Request $request): void
    {
        $user = $this->auth->requireRole(['approver', 'admin']);

        Response::view('admin/corrections', [
            'user' => $user,
            'pending_requests' => $this->corrections->listPending(),
            'flash' => Flash::all(),
            'csrf_tokens' => [
                'correction_decision' => CsrfTokenManager::generateToken('admin_correction'),
                'logout' => CsrfTokenManager::generateToken('logout_form'),
            ],
        ]);
    }

    public function approveCorrection(Request $request): void
    {
        $this->handleCorrectionDecision($request, 'approved');
    }

    public function rejectCorrection(Request $request): void
    {
        $this->handleCorrectionDecision($request, 'rejected');
    }

    public function users(Request $request): void
    {
        $user = $this->auth->requireRole(['admin']);

        Response::view('admin/users', [
            'user' => $user,
            'employees' => $this->employees->listAll(),
            'flash' => Flash::all(),
            'csrf_tokens' => [
                'create_user' => CsrfTokenManager::generateToken('admin_create_user'),
                'logout' => CsrfTokenManager::generateToken('logout_form'),
            ],
        ]);
    }

    public function createUser(Request $request): void
    {
        $admin = $this->auth->requireRole(['admin']);

        if (!CsrfTokenManager::validateToken('admin_create_user', $request->input('_token'))) {
            Flash::push('error', '不正なリクエストが検出されました。');
            Response::redirect('/admin/users');
        }

        $employeeCode = trim((string) $request->input('employee_code'));
        $loginId = trim((string) $request->input('login_id'));
        $displayName = trim((string) $request->input('display_name'));
        $role = (string) $request->input('role', 'employee');
        $password = (string) $request->input('password');

        if ($employeeCode === '' || $loginId === '' || $displayName === '' || $password === '') {
            Flash::push('error', '必要な項目が入力されていません。');
            Response::redirect('/admin/users');
        }

        if (!in_array($role, ['employee', 'approver', 'admin'], true)) {
            Flash::push('error', '役割の指定が不正です。');
            Response::redirect('/admin/users');
        }

        try {
            $this->employees->create([
                'employee_code' => $employeeCode,
                'login_id' => $loginId,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'display_name' => $displayName,
                'role' => $role,
                'active' => 1,
            ]);

            Flash::push('success', "{$displayName} を登録しました。");
        } catch (\Throwable $e) {
            Flash::push('error', 'ユーザーの登録に失敗しました。重複していないか確認してください。');
        }

        Response::redirect('/admin/users');
    }

    public function export(Request $request): void
    {
        $user = $this->auth->requireRole(['approver', 'admin']);

        $month = (string) $request->query('month', $this->currentMonth());
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = $this->currentMonth();
        }

        if ($request->query('download') === '1') {
            $withBom = $request->query('bom') === '1';
            $csv = $this->exportService->generateMonthlyCsv($month, $withBom);
            $filename = sprintf('attendance_%s.csv', str_replace('-', '', $month));
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $csv;
            exit;
        }

        Response::view('admin/export', [
            'user' => $user,
            'month' => $month,
            'flash' => Flash::all(),
            'csrf_tokens' => [
                'logout' => CsrfTokenManager::generateToken('logout_form'),
            ],
        ]);
    }

    private function handleCorrectionDecision(Request $request, string $decision): void
    {
        $user = $this->auth->requireRole(['approver', 'admin']);

        if (!CsrfTokenManager::validateToken('admin_correction', $request->input('_token'))) {
            Flash::push('error', '不正なリクエストが検出されました。');
            Response::redirect('/admin/corrections');
        }

        $id = (int) $request->attribute('id', 0);

        if ($id <= 0) {
            Flash::push('error', '対象が見つかりません。');
            Response::redirect('/admin/corrections');
        }

        try {
            $this->corrections->decide($id, (int) $user['id'], $decision);
            Flash::push('success', "申請 #{$id} を" . ($decision === 'approved' ? '承認' : '却下') . "しました。");
        } catch (RuntimeException $e) {
            Flash::push('error', $e->getMessage());
        } catch (\Throwable $e) {
            Flash::push('error', '申請処理でエラーが発生しました。');
        }

        Response::redirect('/admin/corrections');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findMissingClockOuts(string $date): array
    {
        $result = [];

        foreach ($this->employees->listActive() as $employee) {
            $summary = $this->dailySummaries->findByUserAndDate((int) $employee['id'], $date);

            if ($summary && $summary['clock_in_at'] && empty($summary['clock_out_at'])) {
                $result[] = [
                    'employee' => $employee,
                    'summary' => $summary,
                ];
            }
        }

        return $result;
    }

    private function currentMonth(): string
    {
        $tz = new DateTimeZone(Config::get('app.timezone', 'Asia/Tokyo'));
        return (new DateTimeImmutable('now', $tz))->format('Y-m');
    }

    private function today(): string
    {
        $tz = new DateTimeZone(Config::get('app.timezone', 'Asia/Tokyo'));
        return (new DateTimeImmutable('now', $tz))->format('Y-m-d');
    }
}
