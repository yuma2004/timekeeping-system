# データモデル

## 主テーブル
- `employees`：従業員（社員番号、ログインID、在籍状態）
- `attendance_events`：生イベント  
  - {event_id, user_id, kind(in|out|break_start|break_end), occurred_at(UTC), ip, ua, raw_payload_json, prev_hmac, hmac_link, created_at}
- `daily_summaries`：日次集計（始業・終業・休憩合計・労働分数・スナップショットハッシュ）
- `correction_requests`：修正申請（before/after、理由、審査状態、承認者、決定日時）
- `admin_audit_logs`：管理操作ログ（だれが／何を／いつ）

## 改ざん検知（専門語を先に説明）
- 説明：**つながったハッシュ**で履歴の連続性を守り、後から書き換えがないか確かめる仕組み。  
- 用語名：**ハッシュ連鎖（HMAC-SHA256）**  
- 以後の説明：各イベントに `prev_hmac` と `hmac_link` を持たせ、**日次スナップショット**の値を固定化する。

## インデックス（例）
- `attendance_events(event_id UNIQUE)`：二重登録の防止
- `attendance_events(user_id, occurred_at)`：検索高速化
- `daily_summaries(user_id, work_date UNIQUE)`：日次の一意性
- `correction_requests(user_id, work_date, status)`：審査一覧
