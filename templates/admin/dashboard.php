<?php

/** @var callable $e */
/** @var array $user */
/** @var array $pending_requests */
/** @var array $missing_clockouts */
/** @var array $csrf_tokens */

use App\Support\Config;

$title = '管理ダッシュボード';
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

$correctionToken = $csrf_tokens['correction_decision'] ?? '';

ob_start();
?>
<h1>管理ダッシュボード</h1>
<p>監査対応を想定した最小構成です。打刻の最新状況と未退勤者、修正申請の件数を確認できます。</p>

<section class="grid two">
    <div>
        <h2>修正申請</h2>
        <p>未処理件数: <strong><?= $e((string) count($pending_requests)); ?></strong></p>
        <?php if ($pending_requests): ?>
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>従業員</th>
                    <th>対象日</th>
                    <th>理由</th>
                    <th>申請日時</th>
                    <th>操作</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($pending_requests as $req): ?>
                    <tr>
                        <td>#<?= $e($req['id']); ?></td>
                        <td><?= $e($req['user_id']); ?></td>
                        <td><?= $e($req['work_date']); ?></td>
                        <td><?= nl2br($e($req['reason_text'])); ?></td>
                        <td><?= $formatDateTime($req['created_at'] ?? null); ?></td>
                        <td>
                            <form method="post" action="/admin/corrections/<?= $e($req['id']); ?>/approve" style="display:inline;">
                                <input type="hidden" name="_token" value="<?= $e($correctionToken); ?>">
                                <button type="submit">承認</button>
                            </form>
                            <form method="post" action="/admin/corrections/<?= $e($req['id']); ?>/reject" style="display:inline;">
                                <input type="hidden" name="_token" value="<?= $e($correctionToken); ?>">
                                <button type="submit" class="secondary">却下</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p><a href="/admin/corrections">すべての申請を確認する</a></p>
        <?php else: ?>
            <p>未処理の修正申請はありません。</p>
        <?php endif; ?>
    </div>
    <div>
        <h2>本日の未退勤アラート (<?= $e($today ?? ''); ?>)</h2>
        <?php if ($missing_clockouts): ?>
            <table>
                <thead>
                <tr>
                    <th>従業員</th>
                    <th>始業</th>
                    <th>現在の状態</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($missing_clockouts as $item): ?>
                    <tr>
                        <td><?= $e($item['employee']['display_name']); ?></td>
                        <td><?= $formatDateTime($item['summary']['clock_in_at'] ?? null); ?></td>
                        <td>退勤未登録</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>未退勤の従業員はいません。</p>
        <?php endif; ?>
    </div>
</section>

<section>
    <h2>ショートカット</h2>
    <ul>
        <li><a href="/admin/export">月次CSV出力</a></li>
        <?php if ($user['role'] === 'admin'): ?>
            <li><a href="/admin/users">利用者管理</a></li>
        <?php endif; ?>
    </ul>
</section>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
