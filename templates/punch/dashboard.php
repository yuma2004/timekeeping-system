<?php

/** @var callable $e */
/** @var array $user */
/** @var array $summaries */
/** @var array $today_summary */
/** @var array $last_event */
/** @var array $correction_requests */
/** @var array $csrf_tokens */

use App\Support\Config;

$title = '勤怠ダッシュボード';
$timezone = new DateTimeZone(Config::get('app.timezone', 'Asia/Tokyo'));

$formatDateTime = static function (?string $utc) use ($timezone, $e): string {
    if (!$utc) {
        return '';
    }
    try {
        $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        return $e($dt->setTimezone($timezone)->format('Y-m-d H:i'));
    } catch (\Exception) {
        return '';
    }
};

$formatMinutes = static function (?int $minutes): string {
    if ($minutes === null) {
        return '';
    }
    $hours = intdiv($minutes, 60);
    $mins = $minutes % 60;
    return sprintf('%d時間%02d分', $hours, $mins);
};

$status = '未打刻';
if ($last_event) {
    $statusMap = [
        'in' => '勤務中',
        'out' => '退勤済み',
        'break_start' => '休憩中',
        'break_end' => '勤務中 (休憩戻り)',
    ];
    $status = $statusMap[$last_event['kind']] ?? '不明';
}

$today = $today ?? (new DateTimeImmutable('now', $timezone))->format('Y-m-d');

$correctionMap = [];
foreach ($correction_requests as $req) {
    $correctionMap[$req['work_date']] = $req['status'];
}

ob_start();
?>
<h1><?= $e($user['display_name']); ?>さんの勤怠ダッシュボード</h1>
<p>サーバー時刻で打刻を記録しています。表示は <?= $e($timezone->getName()); ?> 基準です。</p>

<section class="grid two">
    <div>
        <h2>現在の状態</h2>
        <p><strong><?= $e($status); ?></strong></p>
        <p>最新の打刻: <?= $formatDateTime($last_event['occurred_at'] ?? null); ?> (<?= $e($last_event['kind'] ?? ''); ?>)</p>
        <form method="post" action="/punch">
            <input type="hidden" name="_token" value="<?= $e($csrf_tokens['punch'] ?? ''); ?>">
            <div style="display: grid; gap: 0.5rem; grid-template-columns: repeat(2, minmax(0, 1fr));">
                <button type="submit" name="kind" value="in">出勤</button>
                <button type="submit" name="kind" value="out" class="secondary">退勤</button>
                <button type="submit" name="kind" value="break_start" class="secondary">休憩入り</button>
                <button type="submit" name="kind" value="break_end" class="secondary">休憩戻り</button>
            </div>
        </form>
    </div>
    <div>
        <h2>本日のサマリ (<?= $e($today); ?>)</h2>
        <table>
            <tbody>
            <tr>
                <th>始業</th>
                <td><?= $formatDateTime($today_summary['clock_in_at'] ?? null); ?></td>
            </tr>
            <tr>
                <th>終業</th>
                <td><?= $formatDateTime($today_summary['clock_out_at'] ?? null); ?></td>
            </tr>
            <tr>
                <th>休憩</th>
                <td><?= $formatMinutes((int) ($today_summary['break_minutes'] ?? 0)); ?></td>
            </tr>
            <tr>
                <th>実労働</th>
                <td><?= $formatMinutes($today_summary['total_work_minutes'] ?? null); ?></td>
            </tr>
            </tbody>
        </table>
    </div>
</section>

<section>
    <h2><?= $e($month); ?> の日別一覧</h2>
    <table>
        <thead>
        <tr>
            <th>日付</th>
            <th>始業</th>
            <th>終業</th>
            <th>休憩</th>
            <th>労働</th>
            <th>修正申請</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$summaries): ?>
            <tr><td colspan="6">記録がありません。</td></tr>
        <?php endif; ?>
        <?php foreach ($summaries as $summary): ?>
            <tr>
                <td><?= $e($summary['work_date']); ?></td>
                <td><?= $formatDateTime($summary['clock_in_at'] ?? null); ?></td>
                <td><?= $formatDateTime($summary['clock_out_at'] ?? null); ?></td>
                <td><?= $formatMinutes((int) ($summary['break_minutes'] ?? 0)); ?></td>
                <td><?= $formatMinutes($summary['total_work_minutes'] ?? null); ?></td>
                <td><?= $e($correctionMap[$summary['work_date']] ?? 'なし'); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section>
    <h2>修正申請</h2>
    <p>打刻の誤りがある場合は、希望する内容と理由を添えて申請してください。</p>
    <form method="post" action="/me/corrections">
        <input type="hidden" name="_token" value="<?= $e($csrf_tokens['correction'] ?? ''); ?>">
        <label for="work_date">対象日</label>
        <input type="date" id="work_date" name="work_date" value="<?= $e($today); ?>" required>

        <label for="clock_in_at">希望する始業時刻</label>
        <input type="datetime-local" id="clock_in_at" name="clock_in_at">

        <label for="clock_out_at">希望する終業時刻</label>
        <input type="datetime-local" id="clock_out_at" name="clock_out_at">

        <label for="break_minutes">休憩時間 (分)</label>
        <input type="text" id="break_minutes" name="break_minutes" value="60">

        <label for="reason">理由</label>
        <textarea id="reason" name="reason" required placeholder="例）退勤打刻を忘れたため、19:00で登録をお願いします。"></textarea>

        <button type="submit">修正を申請する</button>
    </form>

    <h3>申請履歴</h3>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>対象日</th>
            <th>状態</th>
            <th>理由</th>
            <th>申請日時</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$correction_requests): ?>
            <tr><td colspan="5">申請履歴はありません。</td></tr>
        <?php endif; ?>
        <?php foreach ($correction_requests as $request): ?>
            <tr>
                <td>#<?= $e($request['id']); ?></td>
                <td><?= $e($request['work_date']); ?></td>
                <td><?= $e($request['status']); ?></td>
                <td><?= nl2br($e($request['reason_text'])); ?></td>
                <td><?= $formatDateTime($request['created_at'] ?? null); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
