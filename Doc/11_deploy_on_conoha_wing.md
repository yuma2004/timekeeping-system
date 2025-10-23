# ConoHa WING でのデプロイ手順

1. **ドメインとSSL**  
   - ドメインをWINGに向け、**無料独自SSL**を有効化（自動でhttpsにリダイレクト）。citeturn0search0
2. **PHP設定**  
   - コントロールパネルで**PHPバージョン**や設定を調整。citeturn0search6
3. **DB作成**  
   - コントロールパネルからMySQL 5.7のDBとユーザーを作成（**外部からの接続不可**）。citeturn1view0
4. **コード配置**  
   - `public/` をドキュメントルートに設定。`config/` や `.env.php` はWeb公開領域の外に置く。
5. **WAF**  
   - **WAFをON**にし、遮断ログを定期確認する。citeturn0search4
6. **管理画面の多段防御**  
   - **ディレクトリアクセス制限（Basic認証）**を管理パスに設定。citeturn3search1  
   - `.htaccess` 編集もWINGの管理画面から可能。citeturn3search0
7. **ジョブスケジューラー**  
   - 日次・月次のPHPスクリプトを**ジョブスケジューラー**に登録。citeturn2search0
