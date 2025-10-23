<?php

/** @var callable $e */
/** @var array $employees */
/** @var array $csrf_tokens */

$title = '利用者管理';
$token = $csrf_tokens['create_user'] ?? '';

ob_start();
?>
<h1>利用者管理</h1>
<p>従業員のログインIDと権限を管理します。WINGの管理画面でのBasic認証と併せて多段防御を構成してください。</p>

<section>
    <h2>新規登録</h2>
    <form method="post" action="/admin/users">
        <input type="hidden" name="_token" value="<?= $e($token); ?>">
        <label for="employee_code">従業員コード</label>
        <input type="text" id="employee_code" name="employee_code" required>

        <label for="login_id">ログインID</label>
        <input type="text" id="login_id" name="login_id" required>

        <label for="display_name">表示名</label>
        <input type="text" id="display_name" name="display_name" required>

        <label for="role">権限ロール</label>
        <select id="role" name="role">
            <option value="employee">employee (従業員)</option>
            <option value="approver">approver (承認者)</option>
            <option value="admin">admin (管理者)</option>
        </select>

        <label for="password">初期パスワード</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">登録する</button>
    </form>
</section>

<section>
    <h2>登録済み従業員</h2>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>コード</th>
            <th>ログインID</th>
            <th>表示名</th>
            <th>ロール</th>
            <th>状態</th>
            <th>更新日</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$employees): ?>
            <tr><td colspan="7">従業員が登録されていません。</td></tr>
        <?php endif; ?>
        <?php foreach ($employees as $employee): ?>
            <tr>
                <td><?= $e($employee['id']); ?></td>
                <td><?= $e($employee['employee_code']); ?></td>
                <td><?= $e($employee['login_id']); ?></td>
                <td><?= $e($employee['display_name']); ?></td>
                <td><?= $e($employee['role']); ?></td>
                <td><?= $employee['active'] ? '在籍' : '退職'; ?></td>
                <td><?= $e($employee['updated_at']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
