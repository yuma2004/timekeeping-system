# PHP 実装の骨組み（例）

> 事実：WINGは管理画面から**PHPバージョン切替やphp.ini設定**ができる。citeturn0search6

```php
<?php
// public/index.php （シンプルなフロントコントローラ例）
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../config/env.php'; // .env相当（Web公開領域の外）

use App\Auth;
use App\Controllers\PunchController;
use App\Controllers\AdminController;

session_start();

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

function require_login() {
  if (empty($_SESSION['uid'])) { header('Location: /login'); exit; }
}

if ($path === '/login' && $_SERVER['REQUEST_METHOD']==='GET') { /* ログイン画面HTML */ }
if ($path === '/login' && $_SERVER['REQUEST_METHOD']==='POST') {
  // CSRF検証 + password_verify でログイン
}

if ($path === '/punch' && $_SERVER['REQUEST_METHOD']==='POST') {
  // CSRF検証、種別 in/out/break_start/break_end
  // サーバー時刻で attendance_events に追記
  // prev_hmac を取得し、hmac_link を生成して保存
}

if (strpos($path, '/admin')===0) {
  require_login();
  // さらにディレクトリアクセス制限（Basic認証）をかけると二重の壁にできる（WING機能）。 
  // 画面: ダッシュボード、修正承認、CSV出力など
}

if ($path === '/healthz') { echo 'ok'; exit; }

http_response_code(404); echo 'not found';
```

### セッション保護
- Cookieは `Secure` / `HttpOnly` / `SameSite=Strict`。  
- CSRFトークンは**フォームごと**に生成し、サーバーで検証する。

### CSV出力
- `/admin/export?month=YYYY-MM` でUTF-8（BOM有/無）を選べるようにし、Excel表示の実務に合わせる。
