# コード解説

## ディレクトリ構成
- `public/` … WING 公開ディレクトリに配置するエントリーポイント。`index.php` がルーティングの起点。
- `src/` … アプリ本体。
  - `Application.php` … ルーティング登録と簡易 DI コンテナ。
  - `bootstrap.php` / `autoload.php` … 共通初期化と PSR-4 オートロード。
  - `Controllers/` … HTTP コントローラ (認証 / 打刻 / 管理)。
  - `Services/` … ドメインロジック層 (認証、打刻、日次サマリ、修正、CSV)。
  - `Repositories/` … PDO を用いた永続化層。MySQL 5.7 前提の SQL。
  - `Security/` … CSRF トークン、ハッシュチェーン生成。
  - `Support/` … 共通ユーティリティ (`Config`, `Database`, `View`, `Flash`)。
- `templates/` … シンプルな PHP ビュー。`layout.php` で共通ナビ・スタイルを提供。
- `scripts/` … cron / ジョブスケジューラー向け CLI スクリプト。
- `config/env.php` … 環境変数を PHP 配列にまとめる設定ファイル。
- `docs/` … 実装レポートと本解説。

## HTTP フロー
1. ブラウザからのリクエストは `public/index.php` で受け取り、`App\Application` が `Router` にルートを登録。
2. `Router` はパス / メソッドに応じて該当コントローラを実行。未定義の場合は `Response::text('not found', 404)`。
3. コントローラは `AuthService` で認証状態を確認し、必要に応じてサービス層 (`AttendanceService` / `CorrectionService` 等) を呼び出す。
4. サービス層はリポジトリ経由で PDO を用いた DB 操作を実行。トランザクションは簡潔化のため必要箇所を単発クエリで構成。
5. ビューへ渡すデータは `Response::view()` で描画。`View::render()` はテンプレートに `$e` (エスケープ用クロージャ) と配列を注入。

## 主要クラスの役割
- `AuthService` … `employees` テーブルを参照したログイン／ログアウト処理。パスワードの再ハッシュにも対応。
- `AttendanceService` … 打刻のバリデーション、イベント ID / HMAC の生成、日次サマリ再計算を担当。
- `DailySummaryService` … タイムゾーンを考慮しながら日別サマリを再構築。休憩時間のパースやスナップショットハッシュ生成を担う。
- `CorrectionService` … 修正申請の登録・承認処理。承認時はサマリの上書きと監査ログ追記を行う。
- `ExportService` … 従業員ごとの月次サマリを CSV 文字列として出力 (BOM オプション付き)。
- `CsrfTokenManager` … フォーム単位のワンタイムトークン発行／検証。検証後は必ず破棄することで二重送信を防止。

## セッションと Flash メッセージ
- `bootstrap.php` でセッション cookie 属性 (Secure/HttpOnly/SameSite) を初期化。`Flash::init()` がリクエスト開始時に現在／次回のメッセージを切り替える。
- 成功・失敗メッセージは `Flash::push()` で次リクエストへ引き継ぎ、`templates/layout.php` が種類別にレンダリング。

## HMAC チェーン
- `AttendanceService::recordPunch()` が `EventSigner` を利用して `hash_hmac('sha256', payload + prev_hmac, secret)` を算出。`prev_hmac` は直前の `hmac_link`。
- `DailySummaryService` は当日のイベントを UTC で集計し、`snapshot_hash` にイベントの要素 (event_id / kind / occurred_at / hmac_link) を結合して SHA256 ハッシュ化。
- `config/env.php` の `security.event_hmac_key` を本番値に置き換えることでチェーンの秘密鍵を環境変数から管理。

## CLI スクリプトの使い方
```bash
php scripts/daily_snapshot.php
php scripts/missing_checkout_alert.php
php scripts/monthly_close.php
```
WING のジョブスケジューラーに登録する際は `php8.2` 等、提供バージョンに合わせた実行コマンドを設定してください。

## 拡張ポイント
- `CorrectionService::decide()` は現在サマリの上書きに留めている。必要であれば `attendance_events` へ補正イベントを追記し、チェーンを継続する実装を追加可能。
- CSV のタイムゾーン変換や列構成は `ExportService` で調整できる。外部 BI 連携時はここを起点に拡張。
- 認証強化 (2 要素 / IP 制限) は Apache `.htaccess` や追加の middleware を導入することで対応可能。
