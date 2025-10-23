<?php

/** @var callable $e */
$title = 'ログイン';
ob_start();
?>
<h1>勤怠管理システム ログイン</h1>
<p>付与されたログインIDとパスワードを入力してください。</p>
<form method="post" action="/login">
    <input type="hidden" name="_token" value="<?= $e($csrf_token ?? ''); ?>">
    <label for="login_id">ログインID</label>
    <input type="text" id="login_id" name="login_id" autocomplete="username" required>

    <label for="password">パスワード</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>

    <button type="submit">ログイン</button>
</form>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
