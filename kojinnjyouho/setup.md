# セットアップ手順

## 1. ディレクトリをWebサーバーへ配置

```bash
sudo cp -r /home/afky5/kojinnjyouho /var/www/html/kojinnjyouho
sudo chown -R www-data:www-data /var/www/html/kojinnjyouho
```

## 2. データベース作成

```bash
mysql -u root -p < /var/www/html/kojinnjyouho/create_db.sql
```

## 3. APIキーを設定

`config.php` を開き、以下を自分のOpenAI APIキーに変更：

```php
define('OPENAI_API_KEY', 'your-openai-api-key-here');
```

モデルを変更したい場合（例: gpt-4o-mini に変更してコスト削減）：

```php
define('OPENAI_MODEL', 'gpt-4o-mini');
```

必要に応じてDB接続情報も変更してください。

## 4. Webブラウザで確認

```
http://localhost/kojinnjyouho/
```

## 5. 調査ループを起動

別ターミナルで実行（Ctrl+Cで停止）：

```bash
php /var/www/html/kojinnjyouho/research_loop.php
```

### バックグラウンド実行する場合：

```bash
nohup php /var/www/html/kojinnjyouho/research_loop.php > /tmp/research_loop.log 2>&1 &
echo $! > /tmp/research_loop.pid
```

ログ確認：
```bash
tail -f /tmp/research_loop.log
```

停止：
```bash
kill $(cat /tmp/research_loop.pid)
```

## ファイル構成

```
kojinnjyouho/
├── config.php         — DB設定・Claude API設定
├── create_db.sql      — DBスキーマ
├── index.php          — 一覧画面
├── register.php       — 登録フォーム（記名/匿名）
├── view.php           — 詳細・結果表示
├── research_loop.php  — 自律ループスクリプト（CLI）
├── style.css          — スタイル
└── setup.md           — この手順書
```
