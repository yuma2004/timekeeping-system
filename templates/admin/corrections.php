<?php

/** @var callable $e */
/** @var array $pending_requests */
/** @var array $csrf_tokens */

use App\Support\Config;

$title = '修正申請の審査';
$timezone = new DateTimeZone(Config::get('app.timezone', 'Asia/Tokyo'));
$token = $csrf_tokens['correction_decision'] ?? '';

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

ob_start();
?>
<h1>修正申請の審査</h1>
<p>労働日ごとの修正履歴を確認し、承認または却下を記録します。決定内容は監査ログに残ります。</p>

<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>従業員ID</th>
        <th>対象日</th>
        <th>現状</th>
        <th>申請内容</th>
        <th>理由</th>
        <th>申請日時</th>
        <th>操作</th>
    </tr>
    </thead>
    <tbody>
    <?php if (!$pending_requests): ?>
        <tr><td colspan="8">未処理の申請はありません。</td></tr>
    <?php endif; ?>
    <?php foreach ($pending_requests as $req): ?>
        <?php
        $before = json_decode($req['before_json'] ?? '[]', true) ?: [];
        $after = json_decode($req['after_json'] ?? '[]', true) ?: [];
        ?>
        <tr>
            <td>#<?= $e($req['id']); ?></td>
            <td><?= $e($req['user_id']); ?></td>
            <td><?= $e($req['work_date']); ?></td>
            <td>
                <small>
                    始業: <?= $e($before['clock_in_at'] ?? ''); ?><br>
                    終業: <?= $e($before['clock_out_at'] ?? ''); ?><br>
                    休憩: <?= $e($before['break_minutes'] ?? ''); ?> 分
                </small>
            </td>
            <td>
                <small>
                    始業: <?= $e($after['clock_in_at'] ?? ''); ?><br>
                    終業: <?= $e($after['clock_out_at'] ?? ''); ?><br>
                    休憩: <?= $e($after['break_minutes'] ?? ''); ?> 分
                </small>
            </td>
            <td><?= nl2br($e($req['reason_text'])); ?></td>
            <td><?= $formatDateTime($req['created_at'] ?? null); ?></td>
            <td>
                <form method="post" action="/admin/corrections/<?= $e($req['id']); ?>/approve" style="display:inline;">
                    <input type="hidden" name="_token" value="<?= $e($token); ?>">
                    <button type="submit">承認</button>
                </form>
                <form method="post" action="/admin/corrections/<?= $e($req['id']); ?>/reject" style="display:inline;">
                    <input type="hidden" name="_token" value="<?= $e($token); ?>">
                    <button type="submit" class="secondary">却下</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<p><a href="/admin">ダッシュボードに戻る</a></p>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
