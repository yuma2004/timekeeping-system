<?php

/** @var callable $e */
$title = $title ?? '勤怠管理システム';
$flash = $flash ?? [];
$user = $user ?? null;
$csrfTokens = $csrf_tokens ?? [];

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($title); ?></title>
    <style>
        :root {
            color-scheme: light;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        body {
            margin: 0;
            background: #f6f8fb;
            color: #24292f;
        }
        header {
            background: #1f2933;
            color: #fff;
            padding: 1rem;
        }
        header .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 960px;
            margin: 0 auto;
        }
        header nav a {
            color: #fff;
            margin-right: 1rem;
            text-decoration: none;
            font-size: 0.95rem;
        }
        header nav a:last-child {
            margin-right: 0;
        }
        .logout-form {
            display: inline;
            margin-left: 1rem;
        }
        .logout-form button {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.3);
            color: #fff;
            padding: 0.3rem 0.8rem;
            border-radius: 4px;
            cursor: pointer;
        }
        main {
            max-width: 960px;
            margin: 2rem auto;
            background: #fff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.1);
        }
        h1, h2, h3 {
            margin-top: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
        }
        table th, table td {
            border: 1px solid #d1d9e6;
            padding: 0.5rem;
            text-align: left;
        }
        table th {
            background: #f1f5f8;
        }
        .flash {
            max-width: 960px;
            margin: 1rem auto 0;
            padding: 0.75rem 1rem;
            border-radius: 4px;
        }
        .flash.error { background: #fde8e8; color: #611a15; border: 1px solid #fab1a0; }
        .flash.success { background: #def7ec; color: #03543f; border: 1px solid #31c48d; }
        .flash.info { background: #e1effe; color: #1c3d5a; border: 1px solid #76a9fa; }
        form {
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            margin-bottom: 0.3rem;
            font-weight: 600;
        }
        input[type="text"],
        input[type="password"],
        input[type="datetime-local"],
        select,
        textarea {
            width: 100%;
            padding: 0.5rem;
            border-radius: 4px;
            border: 1px solid #cbd5e0;
            margin-bottom: 0.8rem;
        }
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        button {
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 0.6rem 1.2rem;
            cursor: pointer;
            font-weight: 600;
        }
        button.secondary {
            background: #64748b;
        }
        .grid {
            display: grid;
            gap: 1.5rem;
        }
        .grid.two {
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        }
    </style>
</head>
<body>
<header>
    <div class="container">
        <div>
            <strong>出退勤管理システム</strong>
            <?php if ($user): ?>
                <span style="margin-left: 1rem; font-size: 0.9rem;"><?= $e($user['display_name']); ?> (<?= $e($user['role']); ?>)</span>
            <?php endif; ?>
        </div>
        <nav>
            <?php if ($user): ?>
                <a href="/">従業員ダッシュボード</a>
                <?php if (in_array($user['role'], ['approver', 'admin'], true)): ?>
                    <a href="/admin">管理ダッシュボード</a>
                    <a href="/admin/corrections">修正申請</a>
                    <a href="/admin/export">CSV出力</a>
                <?php endif; ?>
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="/admin/users">利用者管理</a>
                <?php endif; ?>
                <?php if (!empty($csrfTokens['logout'])): ?>
                    <form class="logout-form" method="post" action="/logout">
                        <input type="hidden" name="_token" value="<?= $e($csrfTokens['logout']); ?>">
                        <button type="submit">ログアウト</button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <a href="/login">ログイン</a>
            <?php endif; ?>
        </nav>
    </div>
    <div class="container" style="margin-top: 0.5rem;">
        <small>WING上での運用を想定した最小構成。全通信はHTTPS/WAF前提。</small>
    </div>
</header>

<?php foreach ($flash as $type => $messages): ?>
    <?php foreach ($messages as $message): ?>
        <div class="flash <?= $e($type); ?>">
            <?= $e($message); ?>
        </div>
    <?php endforeach; ?>
<?php endforeach; ?>

<main>
    <?= $content ?? '' ?>
</main>

</body>
</html>
