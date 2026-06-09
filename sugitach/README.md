# 日曜礼拝 出欠管理システム - セットアップ手順

## 前提条件
- Ubuntu（Xserver VPS）
- Apache + PHP 8.x
- MySQL 8.x（インストール・設定済み）
- データベース `church_attendance` 作成済み
- ユーザー `church_user` 作成済み

## セットアップ手順

### 1. ファイルをVPSにアップロード
`church_attendance/` フォルダごとVPSにアップロードしてください。

設置場所の例：
```
/var/www/html/church_attendance/
```

### 2. PHP-MySQL拡張の確認
```bash
php -m | grep pdo_mysql
```
表示されなければインストール：
```bash
sudo apt install php-mysql
sudo systemctl restart apache2
```

### 3. DB接続設定を編集
```bash
nano /var/www/html/church_attendance/config/database.php
```
`DB_PASS` の値を、`church_user` のパスワードに書き換えてください。

### 4. テーブル作成
```bash
mysql -u church_user -p church_attendance < /var/www/html/church_attendance/sql/create_tables.sql
```

### 5. 初期管理者の作成
1. `setup_admin.php` のパスワードを好きな値に書き換え
2. ブラウザで `http://あなたのIP/church_attendance/setup_admin.php` にアクセス
3. 「管理者アカウントを作成しました」と表示されればOK
4. **⚠️ 作成後、setup_admin.php をサーバーから必ず削除してください**

```bash
rm /var/www/html/church_attendance/setup_admin.php
```

### 6. ログイン
ブラウザで `http://あなたのIP/church_attendance/login.php` にアクセスし、
設定したユーザー名とパスワードでログインしてください。

## ファイル構成
```
church_attendance/
├── config/
│   ├── database.php     ← DB接続設定（パスワード書き換え必須）
│   └── .htaccess        ← configフォルダへのWeb直接アクセスを禁止
├── sql/
│   └── create_tables.sql ← テーブル作成SQL
├── includes/
│   └── auth.php          ← 認証共通処理
├── api/
│   ├── roster.php        ← 名簿API
│   ├── save_attendance.php ← 出欠保存API
│   ├── load_attendance.php ← 出欠読み込みAPI
│   └── export_csv.php    ← CSV出力API
├── css/
│   └── style.css         ← メインCSS
├── js/
│   └── app.js            ← フロントエンドJS
├── login.php             ← ログイン画面
├── logout.php            ← ログアウト
├── register.php          ← ユーザー管理（管理者用）
├── index.php             ← メイン画面
├── setup_admin.php       ← 初期管理者作成（使用後削除）
└── README.md             ← この手順書
```

## セキュリティ対策
- パスワードは bcrypt でハッシュ化して保存
- CSRF トークンによるフォーム保護
- ログイン試行回数制限（5回失敗で15分ロック）
- PDO プリペアドステートメントによる SQL インジェクション防止
- htmlspecialchars() による XSS 対策
- session_regenerate_id() によるセッション固定攻撃防止
- config/ フォルダへの直接アクセスを .htaccess で禁止
