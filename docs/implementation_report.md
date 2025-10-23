# 実装結果レポート

## 要件対応状況
- **打刻フォーム**: `/` で出勤・退勤・休憩のワンクリック打刻を実装。サーバー時刻 (UTC) で記録し、表示は `config/env.php` の `app.timezone` (既定: Asia/Tokyo) に合わせて変換。
- **修正申請フロー**: 従業員は `/me/corrections` で対象日・希望時刻・理由を送信。申請は `correction_requests` に保存し、承認者／管理者が `/admin/corrections` から承認・却下可能。
- **監査証跡**: `attendance_events` へ追記専用で記録し、各イベントは `prev_hmac` と `hmac_link` でチェーン化。日次サマリは `daily_summaries.snapshot_hash` として固定化。
- **CSV エクスポート**: `/admin/export` で月次 CSV を生成 (BOM 付与オプションあり)。`scripts/monthly_close.php` でも自動生成し、`storage/exports` に保存。
- **保存期間 / 保守**: MySQL 5.7 前提の SQL (Doc/09_db_schema.sql.md) と整合。`scripts/` 以下に WING ジョブスケジューラー向けバッチを実装（前日スナップショット、退勤抜け検査、月次締め）。
- **セキュリティ**: CSRF トークンをフォーム単位で発行 (`CsrfTokenManager`)。セッション cookie は Secure/HttpOnly/SameSite=Strict。管理画面はアプリ内認証＋Basic 認証を併用想定。

## 主要機能の挙動サマリ
1. **ログイン**  
   - `templates/auth/login.php` から POST `/login`。成功時は `/` にリダイレクト、失敗時は Flash メッセージ表示。
2. **打刻**  
   - POST `/punch` が `AttendanceService::recordPunch` を起動。最新イベントの `hmac_link` をもとに HMAC チェーンを伸長し、日次サマリを再計算。
3. **修正承認**  
   - 承認者が POST `/admin/corrections/{id}/approve|reject` を実行。承認時は `daily_summaries` に再集計値を上書きし、`admin_audit_logs` に記録。
4. **CSV 出力**  
   - GET `/admin/export?month=YYYY-MM&download=1&bom=1` でダウンロード。エクスポート列は仕様書通り (employee_code 等)。
5. **ヘルスチェック**  
   - GET `/healthz` は `ok` を返却。WING 監視に利用可能。

## 運用ジョブ
- `scripts/daily_snapshot.php` (毎日 01:00): 前日分の日次サマリを再計算し、スナップショットハッシュを固定。
- `scripts/missing_checkout_alert.php` (毎日 01:10): 前日分で退勤未登録の従業員を標準出力に列挙。Slack 連携等を想定。
- `scripts/monthly_close.php` (毎月 00:30): 前月分の CSV を `storage/exports` に保存。手動ダウンロード時のベースファイルになる。

## セキュリティ / 監査メモ
- PHP セッションは `Secure` / `HttpOnly` / `SameSite=Strict` を強制。HTTPS 前提。
- CSRF トークンはワンタイム／フォーム単位で検証され、成功・失敗時は Flash メッセージに反映。
- 監査ログ (`admin_audit_logs`) には承認・却下の詳細 JSON を保存。証跡は 5 年保存を想定。
- 修正申請本文・承認結果は `correction_requests.before_json / after_json` として履歴保存。

## 既知の注意点
- 修正承認時のイベント補完はサマリベースで実装している。必要に応じて `attendance_events` へ補正イベントを追加する拡張を検討。
- CSV の時刻は UTC をそのまま出力。給与システム向けに JST 等へ変換する場合は後段ツールでのタイムゾーン変換が必要。
- メール／Slack 通知は標準で同梱していないため、`scripts/missing_checkout_alert.php` の出力を外部連携へ渡す運用を想定。
