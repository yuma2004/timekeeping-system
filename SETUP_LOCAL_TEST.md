# 🚀 ローカルテスト環境セットアップガイド

## 前提条件
- **Docker** と **Docker Compose** がインストール済み
- **Git Bash** または **PowerShell** でコマンド実行可能

## セットアップ手順

### 1. Docker環境を起動
```bash
docker-compose up -d
```

### 2. サーバーが起動するのを待つ（30秒程度）
```bash
docker-compose logs -f
```

コンテナログで以下が表示されたら準備完了：
```
PHP 8.0.0-dev Server running at http://0.0.0.0:8000
```

### 3. ブラウザでアクセス
- **URL**: `http://localhost:8000`

---

## テストユーザー

| ロール | ログインID | パスワード | 用途 |
|-------|----------|----------|------|
| 従業員 | `user1` | `password123` | 打刻テスト |
| 承認者 | `approver1` | `password123` | 修正承認テスト |
| 管理者 | `admin1` | `password123` | 管理画面テスト |

---

## テストシナリオ

### ✅ シナリオ1：従業員が打刻する
1. ブラウザで `http://localhost:8000` にアクセス
2. ログイン画面で `user1` / `password123` を入力
3. 「出勤」ボタンをクリック
4. 「退勤」ボタンをクリック
5. ✅ 本日の打刻が記録されたことを確認

### ✅ シナリオ2：修正申請する
1. ログイン後、自分の記録を表示
2. 日時を修正
3. 理由を入力
4. 「修正申請」をクリック
5. ✅ 申請が「保留中」になったことを確認

### ✅ シナリオ3：承認者が修正を承認する
1. `approver1` でログイン
2. 管理画面で「修正申請」を確認
3. 「承認」をクリック
4. ✅ ステータスが「承認済み」になったことを確認

### ✅ シナリオ4：CSVをエクスポート
1. `admin1` で管理画面にログイン
2. 「エクスポート」をクリック
3. ✅ CSVファイルがダウンロードされることを確認

---

## トラブルシューティング

### ❌ `docker-compose: command not found`
```bash
# Docker Composeをインストール
curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
chmod +x /usr/local/bin/docker-compose
```

### ❌ ポート3306/8000が既に使われている
```bash
# コンテナを停止して削除
docker-compose down

# 別のポートで起動（docker-compose.ymlを編集）
# ports: "3307:3306" など
```

### ❌ DBに接続できない
```bash
# MySQLコンテナのログを確認
docker-compose logs mysql

# DBが起動しているか確認
docker-compose ps
```

---

## テスト終了後

```bash
# コンテナを停止・削除
docker-compose down

# ボリュームも削除（DBデータをリセット）
docker-compose down -v
```
