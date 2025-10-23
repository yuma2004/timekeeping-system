<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\AttendanceRepository;
use App\Repositories\DailySummaryRepository;
use App\Security\CsrfTokenManager;
use App\Services\AttendanceService;
use App\Services\AuthService;
use App\Services\CorrectionService;
use App\Support\Config;
use App\Support\Flash;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class PunchController
{
    public function __construct(
        private AuthService $auth,
        private AttendanceService $attendanceService,
        private CorrectionService $correctionService,
        private DailySummaryRepository $dailySummaries,
        private AttendanceRepository $attendanceRepository
    ) {
    }

    public function dashboard(Request $request): void
    {
        $user = $this->auth->requireLogin();
        $userId = (int) $user['id'];

        $month = $request->query('month', $this->currentMonth());
        if (!preg_match('/^\d{4}-\d{2}$/', (string) $month)) {
            $month = $this->currentMonth();
        }

        $summaries = $this->attendanceService->getMonthlySummaries($userId, $month);
        $corrections = $this->correctionService->listUserRequests($userId, $month);
        $lastEvent = $this->attendanceRepository->findLastEventForUser($userId);
        $today = $this->todayDate();
        $todaySummary = $this->dailySummaries->findByUserAndDate($userId, $today) ?? [];

        Response::view('punch/dashboard', [
            'user' => $user,
            'month' => $month,
            'today' => $today,
            'summaries' => $summaries,
            'today_summary' => $todaySummary,
            'last_event' => $lastEvent,
            'correction_requests' => $corrections,
            'flash' => Flash::all(),
            'csrf_tokens' => [
                'punch' => CsrfTokenManager::generateToken('punch_form'),
                'correction' => CsrfTokenManager::generateToken('correction_form'),
                'logout' => CsrfTokenManager::generateToken('logout_form'),
            ],
        ]);
    }

    public function punch(Request $request): void
    {
        $user = $this->auth->requireLogin();

        if (!CsrfTokenManager::validateToken('punch_form', $request->input('_token'))) {
            Flash::push('error', '不正なリクエストが検出されました。');
            Response::redirect('/');
        }

        $kind = (string) $request->input('kind');

        try {
            $this->attendanceService->recordPunch(
                (int) $user['id'],
                $kind,
                [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'payload' => [
                        'source' => 'web_form',
                    ],
                ]
            );

            Flash::push('success', '打刻を記録しました。');
        } catch (RuntimeException $e) {
            Flash::push('error', $e->getMessage());
        } catch (\Throwable $e) {
            Flash::push('error', '打刻処理で予期せぬエラーが発生しました。');
        }

        Response::redirect('/');
    }

    public function daysJson(Request $request): void
    {
        $user = $this->auth->requireLogin();
        $month = $request->query('month', $this->currentMonth());

        $data = $this->attendanceService->getMonthlySummaries((int) $user['id'], (string) $month);

        Response::json([
            'month' => $month,
            'data' => $data,
        ]);
    }

    public function submitCorrection(Request $request): void
    {
        $user = $this->auth->requireLogin();

        if (!CsrfTokenManager::validateToken('correction_form', $request->input('_token'))) {
            Flash::push('error', '不正なリクエストが検出されました。');
            Response::redirect('/');
        }

        $workDate = (string) $request->input('work_date');
        $clockInRaw = (string) $request->input('clock_in_at');
        $clockOutRaw = (string) $request->input('clock_out_at');
        $clockIn = $clockInRaw !== '' ? $this->localToUtc($clockInRaw) : null;
        $clockOut = $clockOutRaw !== '' ? $this->localToUtc($clockOutRaw) : null;
        $breakMinutes = (int) max(0, (int) $request->input('break_minutes', 0));
        $reason = trim((string) $request->input('reason'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) {
            Flash::push('error', '日付の形式が正しくありません。');
            Response::redirect('/');
        }

        if ($clockInRaw !== '' && $clockIn === null) {
            Flash::push('error', '始業時刻の形式が正しくありません。');
            Response::redirect('/');
        }

        if ($clockOutRaw !== '' && $clockOut === null) {
            Flash::push('error', '終業時刻の形式が正しくありません。');
            Response::redirect('/');
        }

        if ($reason === '') {
            Flash::push('error', '理由を入力してください。');
            Response::redirect('/');
        }

        try {
            $after = [
                'clock_in_at' => $clockIn,
                'clock_out_at' => $clockOut,
                'break_minutes' => $breakMinutes,
            ];

            $this->correctionService->submitRequest(
                (int) $user['id'],
                $workDate,
                $after,
                $reason
            );

            Flash::push('success', '修正申請を受け付けました。承認をお待ちください。');
        } catch (\Throwable $e) {
            Flash::push('error', '修正申請の処理でエラーが発生しました。');
        }

        Response::redirect('/');
    }

    private function currentMonth(): string
    {
        $tz = new DateTimeZone(Config::get('app.timezone', 'Asia/Tokyo'));
        return (new DateTimeImmutable('now', $tz))->format('Y-m');
    }

    private function todayDate(): string
    {
        $tz = new DateTimeZone(Config::get('app.timezone', 'Asia/Tokyo'));
        return (new DateTimeImmutable('now', $tz))->format('Y-m-d');
    }

    private function localToUtc(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $localZone = new DateTimeZone(Config::get('app.timezone', 'Asia/Tokyo'));

        $formats = ['Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s'];

        foreach ($formats as $format) {
            $dt = DateTimeImmutable::createFromFormat($format, $value, $localZone);
            if ($dt instanceof DateTimeImmutable) {
                return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            }
        }

        return null;
    }
}
