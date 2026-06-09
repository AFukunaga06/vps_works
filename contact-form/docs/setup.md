# お問い合わせフォーム 設置手順書

## 1. ファイルのアップロード

contact-form フォルダの中身をすべてサーバーにアップロードします。

```
アップロード先の例：
https://example.com/contact/
```

FTPソフト（FileZilla等）を使い、`contact-form/` の中身をサーバーの任意のディレクトリにアップロードしてください。

---

## 2. config.php の設定

`config.sample.php` をコピーして `config.php` にリネームし、以下を書き換えます。

| 項目 | 説明 |
|---|---|
| SITE_NAME | サイト名・会社名 |
| ADMIN_EMAIL | 問い合わせを受け取るメールアドレス |
| FROM_EMAIL | 自動返信メールの送信元アドレス |
| FROM_NAME | 自動返信メールの送信者名 |

### SMTPを使う場合（Gmailなど）

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'your-address@gmail.com');
define('SMTP_PASS', 'xxxx xxxx xxxx xxxx'); // Googleアプリパスワード
define('SMTP_PORT', 587);
```

※ Googleアプリパスワードは「Googleアカウント → セキュリティ → 2段階認証 → アプリパスワード」から発行します。

### SMTPを使わない場合

SMTP_USER を空欄にするとサーバーの `mail()` 関数で送信します。
多くのレンタルサーバー（エックスサーバー・さくら等）はそのまま動作します。

---

## 3. DBログを使う場合（オプション）

MySQLが使える場合は `create_db.sql` を実行してテーブルを作成します。

```sql
-- phpMyAdmin または SSHで実行
SOURCE create_db.sql;
```

その後 `config.php` の DB設定を記入します。

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database');
define('DB_USER', 'your_user');
define('DB_PASS', 'your_password');
```

---

## 4. 動作確認

1. ブラウザで `index.php` を開く
2. フォームに入力して「確認画面へ」をクリック
3. 確認画面で「送信する」をクリック
4. ADMIN_EMAIL に問い合わせメールが届くことを確認
5. 入力したメールアドレスに自動返信メールが届くことを確認

---

## 5. よくあるエラー

| 症状 | 原因・対処 |
|---|---|
| メールが届かない | SMTP設定を確認。SMTP_USERが空ならmail()で送信されるがサーバー設定を確認 |
| 500エラー | .htaccess が原因の場合あり。.htaccessを削除して試す |
| セッションエラー | PHPのsession保存先のパーミッションを確認 |
| DBに保存されない | DB_NAME が空欄なら保存しない仕様（正常）。入力した場合はDB接続情報を確認 |
