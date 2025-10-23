# リスクと制約

- **共用環境の制約**：長時間の常駐プロセスは使わない前提。HTTP＋cronで完結させる（WINGはジョブスケジューラーを提供）。citeturn2search0
- **外部BI連携の制約**：MySQLは**外部接続不可**。レポートは**CSVエクスポート**経由にする。citeturn1view0
- **管理画面の保護**：Basic認証自体は強固ではないため、**HTTPS必須**かつ**アプリ内認証と併用**する。citeturn0search5

回避策：  
1) HTTPS＋WAF＋多段認証で守る（アプリ認証＋Basic認証＋WAF） 。citeturn0search4turn3search1  
2) CSVの運搬は社内ルールを定め、暗号化ZIP等で保護する。  
3) 監査用ハッシュ連鎖と日次スナップショットで改ざんを検知できるようにする。
