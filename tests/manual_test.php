<?php

declare(strict_types=1);

/**
 * 手動テストスクリプト
 * 出退勤管理システムの基本機能を検証します
 */

require __DIR__ . '/../src/bootstrap.php';

use App\Support\Database;
use App\Repositories\EmployeeRepository;
use App\Services\AuthService;

echo "=== 出退勤管理システム テスト開始 ===\n\n";

try {
    // 1. DB接続テスト
    echo "✓ ステップ1: DB接続テスト\n";
    $pdo = Database::connection();
    echo "  → データベース接続成功\n\n";

    // 2. ユーザー存在確認テスト
    echo "✓ ステップ2: ユーザー存在確認\n";
    $employeeRepo = new EmployeeRepository($pdo);
    $user = $employeeRepo->findByLoginId('user1');
    
    if ($user) {
        echo "  → ユーザー 'user1' が見つかりました\n";
        echo "    ID: {$user['id']}\n";
        echo "    名前: {$user['display_name']}\n";
        echo "    ロール: {$user['role']}\n";
    } else {
        echo "  → ユーザー 'user1' が見つかりません\n";
    }
    echo "\n";

    // 3. 認証サービステスト
    echo "✓ ステップ3: 認証サービステスト\n";
    $authService = new AuthService($employeeRepo);
    $result = $authService->attemptLogin('user1', 'password123');
    
    if ($result) {
        echo "  → ログイン成功（パスワード検証OK）\n";
    } else {
        echo "  → ログイン失敗\n";
    }
    echo "\n";

    // 4. テーブル存在確認
    echo "✓ ステップ4: テーブル存在確認\n";
    $tables = [
        'employees',
        'attendance_events',
        'daily_summaries',
        'correction_requests',
        'admin_audit_logs',
    ];
    
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SELECT 1 FROM {$table} LIMIT 1");
        try {
            $stmt->execute();
            echo "  ✓ {$table}\n";
        } catch (\Exception $e) {
            echo "  ✗ {$table} - {$e->getMessage()}\n";
        }
    }
    echo "\n";

    // 5. CSRF/HMAC設定確認
    echo "✓ ステップ5: セキュリティ設定確認\n";
    $eventHmacKey = \App\Support\Config::get('security.event_hmac_key');
    $csrfKey = \App\Support\Config::get('security.csrf_key');
    echo "  → EVENT_HMAC_KEY: " . (strlen($eventHmacKey) > 0 ? '✓ 設定済み' : '✗ 未設定') . "\n";
    echo "  → CSRF_KEY: " . (strlen($csrfKey) > 0 ? '✓ 設定済み' : '✗ 未設定') . "\n";
    echo "\n";

    echo "=== ✅ 全テスト完了 ===\n";

} catch (\Throwable $e) {
    echo "❌ エラーが発生しました:\n";
    echo "  " . $e->getMessage() . "\n";
    echo "\n詳細:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
