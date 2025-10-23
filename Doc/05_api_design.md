# ルーティング / API（PHP）

## 公開エリア（TLS必須）
- `GET /login`／`POST /login`：ログイン
- `POST /punch`：出勤・退勤・休憩（種別はパラメータ）
- `GET /me/days?month=YYYY-MM`：自分の当月一覧
- `POST /me/corrections`：修正申請の新規登録

## 管理エリア（認証＋多段防御）
- `GET /admin`：ダッシュボード
- `GET /admin/corrections`／`POST /admin/corrections/{id}/approve|reject`
- `GET /admin/export?month=YYYY-MM`：月次CSV
- `GET /admin/users`／`POST /admin/users`：従業員管理
- `POST /admin/edits`：管理者による訂正（**必ず履歴**を残す）

## 共通の守り
- **CSRF対策**（フォームにワンタイムトークン）  
- **入力検証**（サーバー側で必ず実施）  
- **監査ログ**（すべての管理系POSTは記録）  
- **ヘルスチェック**：`GET /healthz`

## 署名やCSRFの説明（専門語の扱い）
- 説明：攻撃者が勝手にフォームを送るのを防ぐため、**正しい画面から送った印**を付けて確認する。  
- 用語名：**CSRFトークン（Cross-Site Request Forgery token）**  
- 以後の説明：全POSTにCSRFトークンを付与し、サーバーが検証する。
